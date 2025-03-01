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

use ILIAS\Cron\Schedule\CronJobScheduleType;

/**
 * Forum notifications
 * @author Michael Jansen <mjansen@databay.de>
 * @author Nadia Matuschek <nmatuschek@databay.de>
 */
class ilForumCronNotification extends ilCronJob
{
    private const KEEP_ALIVE_CHUNK_SIZE = 25;
    private const DEFAULT_MAX_NOTIFICATION_AGE_IN_DAYS = 30;

    /** @var ilForumCronNotificationDataProvider[] */
    private static array $providerObject = [];
    /** @var int[] */
    private static array $deleted_ids_cache = [];
    /** @var array<int, int[]> */
    private static array $ref_ids_by_obj_id = [];
    /** @var array<int, int[]> */
    private static array $accessible_ref_ids_by_user = [];
    /** @var array<int, ilObjCourse|ilObjGroup|null> */
    private static array $container_by_frm_ref_id = [];

    private ilLanguage $lng;
    private ilSetting $settings;
    private ilLogger $logger;
    private ilTree $tree;
    private int $num_sent_messages = 0;
    private ilDBInterface $ilDB;
    private ilForumNotificationCache $notificationCache;
    private \ILIAS\Refinery\Factory $refinery;
    private ilCronManager $cronManager;

    public function __construct(
        ilDBInterface $database = null,
        ilForumNotificationCache $notificationCache = null,
        ilLanguage $lng = null,
        ilSetting $settings = null,
        \ILIAS\Refinery\Factory $refinery = null,
        ilCronManager $cronManager = null
    ) {
        global $DIC;

        $this->settings = $settings ?? new ilSetting('frma');
        $this->lng = $lng ?? $DIC->language();
        $this->ilDB = $database ?? $DIC->database();
        $this->notificationCache = $notificationCache ?? new ilForumNotificationCache();
        $this->refinery = $refinery ?? $DIC->refinery();
        $this->cronManager = $cronManager ?? $DIC->cron()->manager();
    }

    public function getId(): string
    {
        return 'frm_notification';
    }

    public function getTitle(): string
    {
        return $this->lng->txt('cron_forum_notification');
    }

    public function getDescription(): string
    {
        return $this->lng->txt('cron_forum_notification_crob_desc');
    }

    public function getDefaultScheduleType(): CronJobScheduleType
    {
        return CronJobScheduleType::SCHEDULE_TYPE_IN_HOURS;
    }

    public function getDefaultScheduleValue(): ?int
    {
        return 1;
    }

    public function hasAutoActivation(): bool
    {
        return false;
    }

    public function hasFlexibleSchedule(): bool
    {
        return true;
    }

    public function hasCustomSettings(): bool
    {
        return true;
    }

    public function keepAlive(): void
    {
        $this->logger->debug('Sending ping to cron manager ...');
        $this->cronManager->ping($this->getId());
        $this->logger->debug(sprintf('Current memory usage: %s', memory_get_usage(true)));
    }

    public function run(): ilCronJobResult
    {
        global $DIC;

        $this->logger = $DIC->logger()->frm();
        $this->tree = $DIC->repositoryTree();

        $status = ilCronJobResult::STATUS_NO_ACTION;

        $this->lng->loadLanguageModule('forum');

        $this->logger->info('Started forum notification job ...');

        if (!($last_run_datetime = $this->settings->get('cron_forum_notification_last_date'))) {
            $last_run_datetime = null;
        }

        $this->num_sent_messages = 0;
        $cj_start_date = date('Y-m-d H:i:s');

        if ((string) $this->settings->get('max_notification_age', '') === '') {
            $this->logger->info(sprintf(
                'No maximum notification age set, %s days will be used to determine the ' .
                'left interval when querying the relevant forum events.',
                self::DEFAULT_MAX_NOTIFICATION_AGE_IN_DAYS
            ));
        }

        if ($last_run_datetime !== null &&
            checkdate(
                (int) date('m', strtotime($last_run_datetime)),
                (int) date('d', strtotime($last_run_datetime)),
                (int) date('Y', strtotime($last_run_datetime))
            )) {
            $threshold = max(
                strtotime($last_run_datetime),
                strtotime('-' . (int) $this->settings->get('max_notification_age', (string) self::DEFAULT_MAX_NOTIFICATION_AGE_IN_DAYS) . ' days')
            );
        } else {
            $threshold = strtotime('-' . (int) $this->settings->get('max_notification_age', (string) self::DEFAULT_MAX_NOTIFICATION_AGE_IN_DAYS) . ' days');
        }

        $this->logger->info(sprintf('Threshold for forum event determination is: %s', date('Y-m-d H:i:s', $threshold)));

        $threshold_date = date('Y-m-d H:i:s', $threshold);

        $this->sendNotificationForNewPosts($threshold_date);

        $this->sendNotificationForUpdatedPosts($threshold_date);

        $this->sendNotificationForCensoredPosts($threshold_date);

        $this->sendNotificationForUncensoredPosts($threshold_date);

        $this->sendNotificationForDeletedThreads();

        $this->sendNotificationForDeletedPosts();

        $this->settings->set('cron_forum_notification_last_date', $cj_start_date);

        $mess = 'Sent ' . $this->num_sent_messages . ' messages.';

        $this->logger->info($mess);
        $this->logger->info('Finished forum notification job');

        $result = new ilCronJobResult();
        if ($this->num_sent_messages !== 0) {
            $status = ilCronJobResult::STATUS_OK;
            $result->setMessage($mess);
        }

        $result->setStatus($status);

        return $result;
    }

    /**
     * @return list<int>
     */
    protected function getRefIdsByObjId(int $a_obj_id): array
    {
        if (!array_key_exists($a_obj_id, self::$ref_ids_by_obj_id)) {
            self::$ref_ids_by_obj_id[$a_obj_id] = array_values(ilObject::_getAllReferences($a_obj_id));
        }

        return self::$ref_ids_by_obj_id[$a_obj_id];
    }

    protected function getFirstAccessibleRefIdBUserAndObjId(int $a_user_id, int $a_obj_id): int
    {
        global $DIC;
        $ilAccess = $DIC->access();

        if (!array_key_exists($a_user_id, self::$accessible_ref_ids_by_user)) {
            self::$accessible_ref_ids_by_user[$a_user_id] = [];
        }

        if (!array_key_exists($a_obj_id, self::$accessible_ref_ids_by_user[$a_user_id])) {
            $accessible_ref_id = 0;
            foreach ($this->getRefIdsByObjId($a_obj_id) as $ref_id) {
                if ($ilAccess->checkAccessOfUser($a_user_id, 'read', '', $ref_id)) {
                    $accessible_ref_id = $ref_id;
                    break;
                }
            }
            self::$accessible_ref_ids_by_user[$a_user_id][$a_obj_id] = $accessible_ref_id;
        }

        return (int) self::$accessible_ref_ids_by_user[$a_user_id][$a_obj_id];
    }

    public function sendCronForumNotification(ilDBStatement $res, int $notification_type): void
    {
        global $DIC;
        $ilDB = $DIC->database();

        while ($row = $ilDB->fetchAssoc($res)) {
            if ($notification_type === ilForumMailNotification::TYPE_POST_DELETED
                || $notification_type === ilForumMailNotification::TYPE_THREAD_DELETED) {
                // important! save the deleted_id to cache before proceeding getFirstAccessibleRefIdBUserAndObjId !
                self::$deleted_ids_cache[$row['deleted_id']] = $row['deleted_id'];
            }

            if (defined('ANONYMOUS_USER_ID') && (int) $row['user_id'] === ANONYMOUS_USER_ID) {
                continue;
            }

            $ref_id = $this->getFirstAccessibleRefIdBUserAndObjId((int) $row['user_id'], (int) $row['obj_id']);
            if ($ref_id < 1) {
                $this->logger->debug(
                    sprintf(
                        'The recipient with id %s has no "read" permission for object with id %s',
                        $row['user_id'],
                        $row['obj_id']
                    )
                );
                continue;
            }

            $row['ref_id'] = $ref_id;

            $container = $this->determineClosestContainer($ref_id);
            $row['closest_container'] = null;
            if ($container instanceof ilObjCourse || $container instanceof ilObjGroup) {
                $row['closest_container'] = $container;
            }

            $provider_id = isset($row['deleted_id']) ? -((int) $row['deleted_id']) : (int) $row['pos_pk'];
            if ($this->existsProviderObject($provider_id, $notification_type)) {
                self::$providerObject[$provider_id . '_' . $notification_type]->addRecipient((int) $row['user_id']);
            } else {
                $this->addProviderObject($provider_id, $row, $notification_type);
            }
        }

        $usrIdsToPreload = [];
        foreach (self::$providerObject as $provider) {
            if ($provider->getPosAuthorId() !== 0) {
                $usrIdsToPreload[$provider->getPosAuthorId()] = $provider->getPosAuthorId();
            }
            if ($provider->getPosDisplayUserId() !== 0) {
                $usrIdsToPreload[$provider->getPosDisplayUserId()] = $provider->getPosDisplayUserId();
            }
            if ($provider->getPostUpdateUserId() !== 0) {
                $usrIdsToPreload[$provider->getPostUpdateUserId()] = $provider->getPostUpdateUserId();
            }
        }

        ilForumAuthorInformationCache::preloadUserObjects(array_unique($usrIdsToPreload));

        $i = 0;
        foreach (self::$providerObject as $provider) {
            if ($i > 0 && ($i % self::KEEP_ALIVE_CHUNK_SIZE) === 0) {
                $this->keepAlive();
            }

            $recipients = array_unique($provider->getCronRecipients());

            $this->logger->info(
                sprintf(
                    'Trying to send forum notifications for posting id "%s", type "%s" and recipients: %s',
                    $provider->getPostId(),
                    $notification_type,
                    implode(', ', $recipients)
                )
            );

            $mailNotification = new ilForumMailNotification($provider, $this->logger);
            $mailNotification->setIsCronjob(true);
            $mailNotification->setType($notification_type);
            $mailNotification->setRecipients($recipients);

            $mailNotification->send();

            $this->num_sent_messages += count($provider->getCronRecipients());
            $this->logger->info('Sent notifications ... ');

            ++$i;
        }

        $this->resetProviderCache();
    }

    /**
     * @return ilObjCourse|ilObjGroup|null
     */
    public function determineClosestContainer(int $frm_ref_id): ?ilObject
    {
        if (isset(self::$container_by_frm_ref_id[$frm_ref_id])) {
            return self::$container_by_frm_ref_id[$frm_ref_id];
        }

        $ref_id = $this->tree->checkForParentType($frm_ref_id, 'crs');
        if ($ref_id <= 0) {
            $ref_id = $this->tree->checkForParentType($frm_ref_id, 'grp');
        }

        if ($ref_id > 0) {
            /** @var ilObjCourse|ilObjGroup $container */
            $container = ilObjectFactory::getInstanceByRefId($ref_id);
            self::$container_by_frm_ref_id[$frm_ref_id] = $container;
            return $container;
        }

        return null;
    }

    public function existsProviderObject(int $provider_id, int $notification_type): bool
    {
        return isset(self::$providerObject[$provider_id . '_' . $notification_type]);
    }

    /**
     * @param array<string, string|int|float|null> $row
     */
    private function addProviderObject(int $provider_id, array $row, int $notification_type): void
    {
        $tmp_provider = new ilForumCronNotificationDataProvider($row, $notification_type, $this->notificationCache);
        self::$providerObject[$provider_id . '_' . $notification_type] = $tmp_provider;
        self::$providerObject[$provider_id . '_' . $notification_type]->addRecipient((int) $row['user_id']);
    }

    private function resetProviderCache(): void
    {
        self::$providerObject = [];
    }

    public function addToExternalSettingsForm(int $a_form_id, array &$a_fields, bool $a_is_active): void
    {
        if ($a_form_id === ilAdministrationSettingsFormHandler::FORM_FORUM) {
            $a_fields['cron_forum_notification'] = $a_is_active ?
                $this->lng->txt('enabled') :
                $this->lng->txt('disabled');
        }
    }

    public function activationWasToggled(ilDBInterface $db, ilSetting $setting, bool $a_currently_active): void
    {
        $value = 1;
        // propagate cron-job setting to object setting
        if ($a_currently_active) {
            $value = 2;
        }

        $setting->set('forum_notification', (string) $value);
    }

    public function addCustomSettingsToForm(ilPropertyFormGUI $a_form): void
    {
        $this->lng->loadLanguageModule('forum');

        $max_notification_age = new ilNumberInputGUI(
            $this->lng->txt('frm_max_notification_age'),
            'max_notification_age'
        );
        $max_notification_age->setSize(5);
        $max_notification_age->setSuffix($this->lng->txt('frm_max_notification_age_unit'));
        $max_notification_age->setRequired(true);
        $max_notification_age->allowDecimals(false);
        $max_notification_age->setMinValue(1);
        $max_notification_age->setInfo($this->lng->txt('frm_max_notification_age_info'));
        $max_notification_age->setValue(
            $this->settings->get(
                'max_notification_age',
                (string) self::DEFAULT_MAX_NOTIFICATION_AGE_IN_DAYS
            )
        );

        $a_form->addItem($max_notification_age);
    }

    public function saveCustomSettings(ilPropertyFormGUI $a_form): bool
    {
        $this->settings->set(
            'max_notification_age',
            $this->refinery->in()->series([
                $this->refinery->byTrying([
                    $this->refinery->kindlyTo()->int(),
                    $this->refinery->in()->series([
                        $this->refinery->kindlyTo()->float(),
                        $this->refinery->kindlyTo()->int()
                    ])
                ]),
                $this->refinery->kindlyTo()->string()
            ])->transform($a_form->getInput('max_notification_age'))
        );
        return true;
    }

    private function sendNotificationForNewPosts(string $threshold_date): void
    {
        $condition = '
        
			frm_posts.pos_status = %s AND (
				(frm_posts.pos_date >= %s AND frm_posts.pos_date = frm_posts.pos_activation_date) OR 
				(frm_posts.pos_activation_date >= %s AND frm_posts.pos_date < frm_posts.pos_activation_date)
			) ';
        $types = [ilDBConstants::T_INTEGER, ilDBConstants::T_TIMESTAMP, ilDBConstants::T_TIMESTAMP];
        $values = [1, $threshold_date, $threshold_date];

        $res = $this->ilDB->queryF(
            $this->createForumPostSql($condition),
            $types,
            $values
        );

        $this->sendNotification(
            $res,
            'new posting',
            ilForumMailNotification::TYPE_POST_NEW
        );
    }

    private function sendNotificationForUpdatedPosts(string $threshold_date): void
    {
        $condition = '
            frm_notification.interested_events & %s AND
			frm_posts.pos_cens = %s AND frm_posts.pos_status = %s AND
			(frm_posts.pos_update > frm_posts.pos_date AND frm_posts.pos_update >= %s) ';
        $types = [
            ilDBConstants::T_INTEGER,
            ilDBConstants::T_INTEGER,
            ilDBConstants::T_INTEGER,
            ilDBConstants::T_TIMESTAMP
        ];
        $values = [ilForumNotificationEvents::UPDATED, 0, 1, $threshold_date];

        $res = $this->ilDB->queryF(
            $this->createForumPostSql($condition),
            $types,
            $values
        );

        $this->sendNotification(
            $res,
            'updated posting',
            ilForumMailNotification::TYPE_POST_UPDATED
        );
    }

    private function sendNotificationForCensoredPosts(string $threshold_date): void
    {
        $condition = '
            frm_notification.interested_events & %s AND
			frm_posts.pos_cens = %s AND frm_posts.pos_status = %s AND  
            (frm_posts.pos_cens_date >= %s AND frm_posts.pos_cens_date > frm_posts.pos_activation_date ) ';
        $types = [
            ilDBConstants::T_INTEGER,
            ilDBConstants::T_INTEGER,
            ilDBConstants::T_INTEGER,
            ilDBConstants::T_TIMESTAMP
        ];
        $values = [ilForumNotificationEvents::CENSORED, 1, 1, $threshold_date];

        $res = $this->ilDB->queryF(
            $this->createForumPostSql($condition),
            $types,
            $values
        );

        $this->sendNotification(
            $res,
            'censored posting',
            ilForumMailNotification::TYPE_POST_CENSORED
        );
    }

    private function sendNotificationForUncensoredPosts(string $threshold_date): void
    {
        $condition = '
            frm_notification.interested_events & %s AND
			frm_posts.pos_cens = %s AND frm_posts.pos_status = %s AND  
            (frm_posts.pos_cens_date >= %s AND frm_posts.pos_cens_date > frm_posts.pos_activation_date ) ';
        $types = [
            ilDBConstants::T_INTEGER,
            ilDBConstants::T_INTEGER,
            ilDBConstants::T_INTEGER,
            ilDBConstants::T_TIMESTAMP
        ];
        $values = [ilForumNotificationEvents::UNCENSORED, 0, 1, $threshold_date];

        $res = $this->ilDB->queryF(
            $this->createForumPostSql($condition),
            $types,
            $values
        );

        $this->sendNotification(
            $res,
            'uncensored posting',
            ilForumMailNotification::TYPE_POST_UNCENSORED
        );
    }

    private function sendNotificationForDeletedThreads(): void
    {
        $res = $this->ilDB->queryF(
            $this->createSelectOfDeletionNotificationsSql(),
            [ilDBConstants::T_INTEGER, ilDBConstants::T_INTEGER],
            [1, ilForumNotificationEvents::THREAD_DELETED]
        );

        $this->sendDeleteNotifications(
            $res,
            'frm_threads_deleted',
            'deleted threads',
            ilForumMailNotification::TYPE_THREAD_DELETED
        );
    }

    private function sendNotificationForDeletedPosts(): void
    {
        $res = $this->ilDB->queryF(
            $this->createSelectOfDeletionNotificationsSql(),
            [ilDBConstants::T_INTEGER, ilDBConstants::T_INTEGER],
            [0, ilForumNotificationEvents::POST_DELETED]
        );

        $this->sendDeleteNotifications(
            $res,
            'frm_posts_deleted',
            'deleted postings',
            ilForumMailNotification::TYPE_POST_DELETED
        );
    }

    private function sendNotification(ilDBStatement $res, string $actionName, int $notificationType): void
    {
        $numRows = $this->ilDB->numRows($res);
        if ($numRows > 0) {
            $this->logger->info(sprintf('Sending notifications for %s "%s" events ...', $numRows, $actionName));
            $this->sendCronForumNotification($res, $notificationType);
            $this->logger->info(sprintf('Sent notifications for %s ...', $actionName));
        }

        $this->keepAlive();
    }

    private function sendDeleteNotifications(
        ilDBStatement $res,
        string $action,
        string $actionDescription,
        int $notificationType
    ): void {
        $numRows = $this->ilDB->numRows($res);
        if ($numRows > 0) {
            $this->logger->info(sprintf('Sending notifications for %s "%s" events ...', $numRows, $actionDescription));
            $this->sendCronForumNotification($res, $notificationType);
            if (self::$deleted_ids_cache !== []) {
                $this->ilDB->manipulate(
                    'DELETE FROM frm_posts_deleted WHERE ' . $this->ilDB->in(
                        'deleted_id',
                        self::$deleted_ids_cache,
                        false,
                        ilDBConstants::T_INTEGER
                    )
                );
                $this->logger->info('Deleted obsolete entries of table "' . $action . '" ...');
            }
            $this->logger->info(sprintf('Sent notifications for %s ...', $actionDescription));
        }

        $this->keepAlive();
    }

    private function createForumPostSql(string $condition): string
    {
        return '
			SELECT 	frm_threads.thr_subject thr_subject,
					frm_data.top_name top_name,
					frm_data.top_frm_fk obj_id,
					frm_notification.user_id user_id,
					frm_threads.thr_pk thread_id,
					frm_posts.*
			FROM 	frm_notification, frm_posts, frm_threads, frm_data, frm_posts_tree
			WHERE	frm_posts.pos_thr_fk = frm_threads.thr_pk AND ' . $condition . '
			AND 	((frm_threads.thr_top_fk = frm_data.top_pk AND 	frm_data.top_frm_fk = frm_notification.frm_id)
					OR (frm_threads.thr_pk = frm_notification.thread_id
			AND 	frm_data.top_pk = frm_threads.thr_top_fk) )
			AND 	frm_posts.pos_author_id != frm_notification.user_id
			AND     frm_posts_tree.pos_fk = frm_posts.pos_pk AND frm_posts_tree.parent_pos != 0
			ORDER BY frm_posts.pos_date ASC';
    }

    private function createSelectOfDeletionNotificationsSql(): string
    {
        return '
			SELECT 	frm_posts_deleted.thread_title thr_subject,
					frm_posts_deleted.forum_title  top_name,
					frm_posts_deleted.obj_id obj_id,
					frm_notification.user_id user_id,
					frm_posts_deleted.pos_display_user_id,
					frm_posts_deleted.pos_usr_alias,
					frm_posts_deleted.deleted_id,
					frm_posts_deleted.post_date pos_date,
					frm_posts_deleted.post_title pos_subject,
					frm_posts_deleted.post_message pos_message,
					frm_posts_deleted.deleted_by
					
			FROM 	frm_notification, frm_posts_deleted
			
			WHERE 	( frm_posts_deleted.obj_id = frm_notification.frm_id
					OR frm_posts_deleted.thread_id = frm_notification.thread_id)
			AND 	frm_posts_deleted.pos_display_user_id != frm_notification.user_id
			AND 	frm_posts_deleted.is_thread_deleted = %s
			AND     frm_notification.interested_events & %s
			ORDER BY frm_posts_deleted.post_date ASC';
    }
}
