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
use ILIAS\Data\Clock\ClockFactory;

class ilCronManagerImpl implements ilCronManager
{
    public function __construct(
        private readonly ilCronJobRepository $cronRepository,
        private readonly ilDBInterface $db,
        private readonly ilSetting $settings,
        private readonly ilLogger $logger,
        private readonly ClockFactory $clock_factory
    ) {
    }

    private function getMicrotime(): float
    {
        return ((int) $this->clock_factory->system()->now()->format('Uu')) / 1000000;
    }

    public function runActiveJobs(ilObjUser $actor): void
    {
        $this->logger->info('CRON - batch start');

        $ts = $this->clock_factory->system()->now()->getTimestamp();
        $this->settings->set('last_cronjob_start_ts', (string) $ts);

        $useRelativeDates = ilDatePresentation::useRelativeDates();
        ilDatePresentation::setUseRelativeDates(false);
        $this->logger->info(sprintf(
            'Set last datetime to: %s',
            ilDatePresentation::formatDate(new ilDateTime($ts, IL_CAL_UNIX))
        ));
        $this->logger->info(sprintf(
            'Verification of last run datetime (read from database): %s',
            ilDatePresentation::formatDate(
                new ilDateTime(ilSetting::_lookupValue('common', 'last_cronjob_start_ts'), IL_CAL_UNIX)
            )
        ));
        ilDatePresentation::setUseRelativeDates($useRelativeDates);

        // ilLink::_getStaticLink() should work in crons
        if (!defined('ILIAS_HTTP_PATH')) {
            define('ILIAS_HTTP_PATH', ilUtil::_getHttpPath());
        }

        // system
        foreach ($this->cronRepository->getCronJobData(null, false) as $row) {
            $job = $this->cronRepository->getJobInstanceById($row['job_id']);
            if ($job instanceof ilCronJob) {
                // #18411 - we are NOT using the initial job data as it might be outdated at this point
                $this->runJob($job, $actor);
            }
        }

        // plugins
        foreach ($this->cronRepository->getPluginJobs(true) as $item) {
            // #18411 - we are NOT using the initial job data as it might be outdated at this point
            $this->runJob($item[0], $actor);
        }

        $this->logger->info('CRON - batch end');
    }

    public function runJobManual(string $jobId, ilObjUser $actor): bool
    {
        $result = false;

        $this->logger->info('CRON - manual start (' . $jobId . ')');

        $job = $this->cronRepository->getJobInstanceById($jobId);
        if ($job instanceof ilCronJob) {
            if ($job->isManuallyExecutable()) {
                $result = $this->runJob($job, $actor, null, true);
            } else {
                $this->logger->info('CRON - job ' . $jobId . ' is not intended to be executed manually');
            }
        } else {
            $this->logger->info('CRON - job ' . $jobId . ' seems invalid or is inactive');
        }

        $this->logger->info('CRON - manual end (' . $jobId . ')');

        return $result;
    }

    /**
     * Run single cron job (internal)
     * @internal
     */
    private function runJob(ilCronJob $job, ilObjUser $actor, ?array $jobData = null, bool $isManualExecution = false): bool
    {
        $did_run = false;

        if (null === $jobData) {
            // aquire "fresh" job (status) data
            $jobsData = $this->cronRepository->getCronJobData($job->getId());
            $jobData = array_pop($jobsData);
        }

        $job->setDateTimeProvider(function (): DateTimeImmutable {
            return $this->clock_factory->system()->now();
        });

        // already running?
        if ($jobData['alive_ts']) {
            $this->logger->info('CRON - job ' . $jobData['job_id'] . ' still running');

            $cut = 60 * 60 * 3;

            // is running (and has not pinged) for 3 hours straight, we assume it crashed
            if ($this->clock_factory->system()->now()->getTimestamp() - ((int) $jobData['alive_ts']) > $cut) {
                $this->cronRepository->updateRunInformation($jobData['job_id'], 0, 0);
                $this->deactivateJob($job, $actor); // #13082

                $result = new ilCronJobResult();
                $result->setStatus(ilCronJobResult::STATUS_CRASHED);
                $result->setCode(ilCronJobResult::CODE_SUPPOSED_CRASH);
                $result->setMessage('Cron job deactivated because it has been inactive for 3 hours');

                $this->cronRepository->updateJobResult(
                    $job,
                    $this->clock_factory->system()->now(),
                    $actor,
                    $result,
                    $isManualExecution
                );

                $this->logger->info('CRON - job ' . $jobData['job_id'] . ' deactivated (assumed crash)');
            }
        } // initiate run?
        elseif ($job->isDue(
            $jobData['job_result_ts'] ? (new DateTimeImmutable(
                '@' . $jobData['job_result_ts']
            ))->setTimezone($this->clock_factory->system()->now()->getTimezone()) : null,
            is_numeric($jobData['schedule_type']) ? CronJobScheduleType::tryFrom((int) $jobData['schedule_type']) : null,
            $jobData['schedule_value'] ? (int) $jobData['schedule_value'] : null,
            $isManualExecution
        )) {
            $this->logger->info('CRON - job ' . $jobData['job_id'] . ' started');

            $this->cronRepository->updateRunInformation(
                $jobData['job_id'],
                $this->clock_factory->system()->now()->getTimestamp(),
                $this->clock_factory->system()->now()->getTimestamp()
            );

            $ts_in = $this->getMicrotime();
            try {
                $result = $job->run();
            } catch (Throwable $e) {
                $result = new ilCronJobResult();
                $result->setStatus(ilCronJobResult::STATUS_CRASHED);
                $result->setMessage(
                    ilStr::subStr(sprintf('Exception: %s / %s', $e->getMessage(), $e->getTraceAsString()), 0, 400)
                );

                $this->logger->error($e->getMessage());
                $this->logger->error($e->getTraceAsString());
            } finally {
                $ts_dur = $this->getMicrotime() - $ts_in;
            }

            if ($result->getStatus() === ilCronJobResult::STATUS_INVALID_CONFIGURATION) {
                $this->deactivateJob($job, $actor);
                $this->logger->info('CRON - job ' . $jobData['job_id'] . ' invalid configuration');
            } else {
                // success!
                $did_run = true;
            }

            $result->setDuration($ts_dur);

            $this->cronRepository->updateJobResult(
                $job,
                $this->clock_factory->system()->now(),
                $actor,
                $result,
                $isManualExecution
            );
            $this->cronRepository->updateRunInformation($jobData['job_id'], 0, 0);

            $this->logger->info('CRON - job ' . $jobData['job_id'] . ' finished');
        } else {
            $this->logger->info('CRON - job ' . $jobData['job_id'] . ' returned status inactive');
        }

        return $did_run;
    }

    public function resetJob(ilCronJob $job, ilObjUser $actor): void
    {
        $result = new ilCronJobResult();
        $result->setStatus(ilCronJobResult::STATUS_RESET);
        $result->setCode(ilCronJobResult::CODE_MANUAL_RESET);
        $result->setMessage('Cron job re-activated by admin');

        $this->cronRepository->updateJobResult(
            $job,
            $this->clock_factory->system()->now(),
            $actor,
            $result,
            true
        );
        $this->cronRepository->resetJob($job);

        $this->activateJob($job, $actor, true);
    }

    public function activateJob(ilCronJob $job, ilObjUser $actor, bool $wasManuallyExecuted = false): void
    {
        $this->cronRepository->activateJob($job, $this->clock_factory->system()->now(), $actor, $wasManuallyExecuted);
        $job->activationWasToggled($this->db, $this->settings, true);
    }

    public function deactivateJob(ilCronJob $job, ilObjUser $actor, bool $wasManuallyExecuted = false): void
    {
        $this->cronRepository->deactivateJob($job, $this->clock_factory->system()->now(), $actor, $wasManuallyExecuted);
        $job->activationWasToggled($this->db, $this->settings, false);
    }

    public function isJobActive(string $jobId): bool
    {
        $jobs_data = $this->cronRepository->getCronJobData($jobId);

        return $jobs_data !== [] && $jobs_data[0]['job_status'];
    }

    public function isJobInactive(string $jobId): bool
    {
        $jobs_data = $this->cronRepository->getCronJobData($jobId);

        return $jobs_data !== [] && !((bool) $jobs_data[0]['job_status']);
    }

    public function ping(string $jobId): void
    {
        $this->db->manipulateF(
            'UPDATE cron_job SET alive_ts = %s WHERE job_id = %s',
            ['integer', 'text'],
            [$this->clock_factory->system()->now()->getTimestamp(), $jobId]
        );
    }
}
