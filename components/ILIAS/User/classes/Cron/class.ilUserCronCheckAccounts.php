<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

declare(strict_types=1);

use ILIAS\Cron\Job\Schedule\JobScheduleType;
use ILIAS\Cron\Job\JobResult;
use ILIAS\Cron\CronJob;

/**
 * This cron send notifications about expiring user accounts
 * @author  Stefan Meyer <meyer@leifos.com>
 */
class ilUserCronCheckAccounts extends CronJob
{
    protected int $counter = 0;

    private ilDBInterface $db;
    private ilLanguage $lng;
    private ilComponentLogger $log;

    public function __construct()
    {
        /** @var ILIAS\DI\Container $DIC */
        global $DIC;

        if (isset($DIC['ilDB'])) {
            $this->db = $DIC['ilDB'];
        }

        if (isset($DIC['lng'])) {
            $this->lng = $DIC['lng'];
        }

        if (isset($DIC['ilDB'])) {
            $this->log = $DIC['ilLog'];
        }
    }

    public function getId(): string
    {
        return 'user_check_accounts';
    }

    public function getTitle(): string
    {
        return $this->lng->txt('check_user_accounts');
    }

    public function getDescription(): string
    {
        return $this->lng->txt('check_user_accounts_desc');
    }

    public function getDefaultScheduleType(): JobScheduleType
    {
        return JobScheduleType::DAILY;
    }

    public function getDefaultScheduleValue(): ?int
    {
        return null;
    }

    public function hasAutoActivation(): bool
    {
        return false;
    }

    public function hasFlexibleSchedule(): bool
    {
        return false;
    }

    public function run(): JobResult
    {
        $status = JobResult::STATUS_NO_ACTION;

        $now = time();
        $two_weeks_in_seconds = $now + (60 * 60 * 24 * 14); // #14630

        // all users who are currently active and expire in the next 2 weeks
        $query = 'SELECT usr_id, login, time_limit_until ' .
            'FROM usr_data ' .
            'WHERE time_limit_message = "0" ' .
            'AND time_limit_unlimited = "0" ' .
            'AND time_limit_from < ' . $this->db->quote($now, 'integer') . ' ' .
            'AND time_limit_until > ' . $this->db->quote($now, 'integer') . ' ' .
            'AND time_limit_until < ' . $this->db->quote($two_weeks_in_seconds, 'integer');

        $res = $this->db->query($query);

        while ($row = $this->db->fetchObject($res)) {
            $expires = $row->time_limit_until;
            $login = $row->login;
            $usr_id = $row->usr_id;

            $lng = ilLanguageFactory::_getLanguageOfUser($usr_id);
            $lng->loadLanguageModule('mail');

            $salutation = ilMail::getSalutation($usr_id, $lng);

            $body = $salutation . "\n\n";
            $body .= sprintf(
                $lng->txt('account_expires_body'),
                $login,
                ILIAS_HTTP_PATH,
                date('Y-m-d H:i', $expires)
            );

            $body .= "\n\n" . ilMail::_getAutoGeneratedMessageString($lng);

            // force email
            $mail = new ilMail(ANONYMOUS_USER_ID);
            $mail->enqueue(
                ilObjUser::_lookupEmail($usr_id),
                '',
                '',
                $lng->txt('account_expires_subject'),
                $body,
                []
            );

            // set status 'mail sent'
            $query = 'UPDATE usr_data SET time_limit_message = "1" WHERE usr_id = "' . $usr_id . '"';
            $this->db->query($query);

            // Send log message
            $this->log->write('Cron: (checkUserAccounts()) sent message to ' . $login . '.');

            $this->counter++;
        }

        $this->checkNotConfirmedUserAccounts();

        if ($this->counter) {
            $status = JobResult::STATUS_OK;
        }
        $result = new JobResult();
        $result->setStatus($status);
        return $result;
    }

    protected function checkNotConfirmedUserAccounts(): void
    {
        $registration_settings = new ilRegistrationSettings();

        $query = 'SELECT usr_id FROM usr_data '
               . 'WHERE (reg_hash IS NOT NULL AND reg_hash != %s)'
               . 'AND active = %s '
               . 'AND create_date < %s';
        $res = $this->db->queryF(
            $query,
            ['text', 'integer', 'timestamp'],
            ['', 0, date('Y-m-d H:i:s', time() - $registration_settings->getRegistrationHashLifetime())]
        );
        while ($row = $this->db->fetchAssoc($res)) {
            $user = ilObjectFactory::getInstanceByObjId((int) $row['usr_id']);
            $user->delete();
            $this->log->write('Cron: Deleted ' . $user->getLogin() . ' [' . $user->getId() . '] ' . __METHOD__);

            $this->counter++;
        }
    }
}
