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

namespace ILIAS\BackgroundTasks\Implementation\Persistence;

use ILIAS\BackgroundTasks\Bucket;
use ILIAS\BackgroundTasks\BucketMeta;
use ILIAS\BackgroundTasks\Exceptions\BucketNotFoundException;
use ILIAS\BackgroundTasks\Exceptions\SerializationException;
use ILIAS\BackgroundTasks\Implementation\Bucket\BasicBucket;
use ILIAS\BackgroundTasks\Implementation\Bucket\BasicBucketMeta;
use ILIAS\BackgroundTasks\Persistence;
use ILIAS\BackgroundTasks\Task;
use ILIAS\BackgroundTasks\Value;
use ILIAS\BackgroundTasks\Implementation\Bucket\State;

class BasicPersistence implements Persistence
{
    protected static BasicPersistence $instance;
    protected static array $buckets = [];
    protected \ilDBInterface $db;
    protected \SplObjectStorage $bucketHashToObserverContainerId;
    protected \SplObjectStorage $taskHashToTaskContainerId;
    protected \SplObjectStorage $valueHashToValueContainerId;
    protected ?\arConnector $connector = null;
    protected static array $tasks = [];

    public static function instance(\ilDBInterface $db): \ILIAS\BackgroundTasks\Implementation\Persistence\BasicPersistence
    {
        if (!isset(self::$instance)) {
            self::$instance = new BasicPersistence($db);
        }

        return self::$instance;
    }

    public function __construct(\ilDBInterface $db)
    {
        $this->db = $db;
        $this->valueHashToValueContainerId = new \SplObjectStorage();
        $this->bucketHashToObserverContainerId = new \SplObjectStorage();
        $this->taskHashToTaskContainerId = new \SplObjectStorage();
    }

    protected function gc(): void
    {
        $atom = $this->db->buildAtomQuery();

        $atom->addTableLock('il_bt_bucket');
        $atom->addTableLock('il_bt_task');
        $atom->addTableLock('il_bt_value');
        $atom->addTableLock('il_bt_value_to_task');
        $atom->addQueryCallable(function (\ilDBInterface $db): void {
            $this->db->manipulateF(
                "DELETE FROM il_bt_bucket WHERE user_id = %s AND (state = %s OR state = %s)",
                ['integer', 'integer', 'integer'],
                [defined('ANONYMOUS_USER_ID') ? \ANONYMOUS_USER_ID : 13, State::FINISHED, State::USER_INTERACTION]
            );

            // remove old finished buckets
            $this->db->manipulateF(
                "DELETE FROM il_bt_bucket WHERE state = %s AND last_heartbeat < %s",
                ['integer', 'integer'],
                [State::FINISHED, time() - 60 * 60 * 24 * 30] // older than 30 days
            );

            // remove old buckets with other states
            $this->db->manipulateF(
                "DELETE FROM il_bt_bucket WHERE state != %s AND last_heartbeat < %s",
                ['integer', 'integer'],
                [State::FINISHED, time() - 60 * 60 * 24 * 180] // older than 180 days
            );

            // remove tasks without a bucket
            $this->db->manipulate(
                "DELETE il_bt_task FROM il_bt_task LEFT JOIN il_bt_bucket ON il_bt_bucket.id = il_bt_task.bucket_id WHERE il_bt_bucket.id IS NULL;"
            );

            // remove value to bucket links without a bucket
            $this->db->manipulate(
                "DELETE il_bt_value_to_task FROM il_bt_value_to_task LEFT JOIN il_bt_bucket ON il_bt_bucket.id = il_bt_value_to_task.bucket_id WHERE il_bt_bucket.id IS NULL;"
            );

            // remove value to bucket links without a task
            $this->db->manipulate(
                "DELETE il_bt_value_to_task FROM il_bt_value_to_task LEFT JOIN il_bt_task ON il_bt_task.id = il_bt_value_to_task.task_id WHERE il_bt_task.id IS NULL;"
            );

            // remove values without a task
            $this->db->manipulate(
                "DELETE il_bt_value FROM il_bt_value LEFT JOIN il_bt_value_to_task ON il_bt_value_to_task.task_id = il_bt_value.id WHERE il_bt_value_to_task.id IS NULL;"
            );
        });
        $atom->run();
    }

    public function setConnector(\arConnector $c): void
    {
        $this->connector = $c;
    }

    /**
     * Fully updates or creates an Observer and all its tasks into the database.
     * @param Bucket $bucket The bucket you want to save.
     */
    public function saveBucketAndItsTasks(Bucket $bucket): void
    {
        $bucket->checkIntegrity();

        $this->saveObserver($bucket);
    }

    /**
     * Updates only the bucket! Use this if e.g. the percentage or the current task changes.
     */
    public function updateBucket(Bucket $bucket): void
    {
        $bucketContainer = new BucketContainer($this->getBucketContainerId($bucket), $this->connector);

        // The basic information about the task.
        $bucketContainer->setUserId($bucket->getUserId());
        $bucketContainer->setState($bucket->getState());
        $bucketContainer->setTotalNumberoftasks(count($bucket->getTask()->unfoldTask()));
        $bucketContainer->setPercentage($bucket->getOverallPercentage());
        $bucketContainer->setTitle($bucket->getTitle());
        $bucketContainer->setLastHeartbeat($bucket->getLastHeartbeat());
        $bucketContainer->setDescription($bucket->getDescription());
        $bucketContainer->setCurrentTaskid($this->getTaskContainerId($bucket->getCurrentTask()));
        $bucketContainer->setRootTaskid($this->getTaskContainerId($bucket->getTask()));

        // Save and store the container to bucket instance.
        $bucketContainer->update();
    }

    /**
     * @inheritdoc
     */
    public function getBucketIdsOfUser(int $user_id, string $order_by = "id", string $order_direction = "ASC"): array
    {
        // Garbage Collection
        $random = new \ilRandom();

        if($random->int(1, 100) === 1) {
            $this->gc();
        }

        return BucketContainer::where(['user_id' => $user_id])
                              ->orderBy($order_by, $order_direction)
                              ->getArray(null, 'id');
    }

    /**
     * @return BucketMeta[]
     */
    public function getBucketMetaOfUser(int $user_id): array
    {
        $buckets = BucketContainer::where(['user_id' => $user_id])->get();

        return array_map(function (BucketContainer $bucketContainer): \ILIAS\BackgroundTasks\Implementation\Bucket\BasicBucketMeta {
            $bucketMeta = new BasicBucketMeta();

            $bucketMeta->setUserId($bucketContainer->getUserId());
            $bucketMeta->setState($bucketContainer->getState());
            $bucketMeta->setTitle($bucketContainer->getTitle());
            $bucketMeta->setDescription($bucketContainer->getDescription());
            $bucketMeta->setOverallPercentage($bucketContainer->getPercentage());

            return $bucketMeta;
        }, $buckets);
    }

    /**
     * @inheritdoc
     */
    public function getBucketIdsByState(int $state): array
    {
        $buckets = BucketContainer::where(['state' => $state])->get();

        return array_map(fn (BucketContainer $bucket_container): int => $bucket_container->getId(), $buckets);
    }

    /**
     * @param Bucket $bucket The bucket we want to save.
     *                       This will recursivly save the Observer.
     */
    protected function saveObserver(Bucket $bucket): void
    {
        // If the instance has a known container we use it, otherwise we create a new container.
        if ($this->bucketHashToObserverContainerId->contains($bucket)) {
            $bucketContainer = new BucketContainer($this->bucketHashToObserverContainerId[$bucket], $this->connector);
        } else {
            $bucketContainer = new BucketContainer(0, $this->connector);
        }

        // The basic information about the task.
        $bucketContainer->setUserId($bucket->getUserId());
        $bucketContainer->setState($bucket->getState());
        $bucketContainer->setTitle($bucket->getTitle());
        $bucketContainer->setDescription($bucket->getDescription());
        $bucketContainer->setTotalNumberoftasks(count($bucket->getTask()->unfoldTask()));
        $bucketContainer->setPercentage($bucket->getOverallPercentage());

        // We want to store the bucket ID in every sub task and value. Thus we need to create an id if not available yet.
        if (!$bucketContainer->getId()) {
            $bucketContainer->create();
        }

        // The recursive part.
        $this->saveTask($bucket->getTask(), $bucketContainer->getId());
        if (!$bucket->hasCurrentTask()) {
            $bucket->setCurrentTask($bucket->getTask());
        }
        $bucketContainer->setCurrentTaskid($this->getTaskContainerId($bucket->getCurrentTask()));
        $bucketContainer->setRootTaskid($this->getTaskContainerId($bucket->getTask()));

        // Save and store the container to bucket instance.
        $bucketContainer->save();
        $this->bucketHashToObserverContainerId[$bucket] = $bucketContainer->getId();
    }

    /**
     * @param Task $task     The task to save.
     * @param int  $bucketId The bucket id is needed as we want some control over what task belongs
     *                       to what batch.
     *                       This will recursivly save a task.
     */
    protected function saveTask(Task $task, int $bucketId): void
    {
        // If the instance has a known container we use it, otherwise we create a new container.
        if ($this->taskHashToTaskContainerId->contains($task)) {
            $taskContainer = new TaskContainer($this->taskHashToTaskContainerId[$task]);
        } else {
            $taskContainer = new TaskContainer(0);
        }

        // The basic information about the task.
        $taskContainer->setType($task->getType());
        $taskContainer->setBucketId($bucketId);
        $reflection = new \ReflectionClass(get_class($task));
        $taskContainer->setClassName(get_class($task));

        // Recursivly save the inputs and link them to this task.
        foreach ($task->getInput() as $k => $input) {
            $this->saveValue($input, $bucketId, $k);
        }
        $this->saveValueToTask($task, $taskContainer, $bucketId);

        // Save and store the container to the task instance.
        $taskContainer->save();
        $this->taskHashToTaskContainerId[$task] = $taskContainer->getId();
    }

    /**
     * Save all input parameters to a task.
     * @param Task          $task          The task containing the inputs
     * @param TaskContainer $taskContainer The container of the task. This is needed to link the
     *                                     ids and delete old links.
     */
    protected function saveValueToTask(Task $task, TaskContainer $taskContainer, int $bucketId): void
    {
        // If we have previous values to task associations we delete them.
        if ($taskContainer->getId() !== 0) {
            /** @var ValueToTaskContainer[] $olds */
            $olds = ValueToTaskContainer::where(['task_id' => $taskContainer->getId()])->get();
            foreach ($olds as $old) {
                $old->delete();
            }
        } else {
            // We need a valid ID to link the inputs
            $taskContainer->save();
        }

        // We create the new 1 to n relation.
        foreach ($task->getInput() as $k => $inputValue) {
            $v = new ValueToTaskContainer(0, $this->connector);
            $v->setTaskId($taskContainer->getId());
            $v->setPosition($k);
            $v->setBucketId($bucketId);
            $v->setValueId($this->getValueContainerId($inputValue));
            $v->save();
        }
    }

    /**
     * @param Value $value    The value
     * @param int   $bucketId The bucket id, we need it to have an overview of all values belonging
     *                        to a batch.
     *                        Stores the value recursively.
     */
    protected function saveValue(Value $value, int $bucketId, int $position): void
    {
        // If we have previous values to task associations we delete them.
        if ($this->valueHashToValueContainerId->contains($value)) {
            $valueContainer = new ValueContainer($this->valueHashToValueContainerId[$value], $this->connector);
        } else {
            $valueContainer = new ValueContainer(0, $this->connector);
        }
        $valueContainer->setClassName(get_class($value));
        // bugfix mantis 23503
        // $absolute_class_path = $reflection->getFileName();
        // $relative_class_path = str_replace(ILIAS_ABSOLUTE_PATH,".",$absolute_class_path);
        // $valueContainer->setClassPath($relative_class_path);
        $valueContainer->setType($value->getType());
        $valueContainer->setHasParenttask($value->hasParentTask());
        $valueContainer->setBucketId($bucketId);
        $valueContainer->setPosition($position);
        $valueContainer->setHash($value->getHash());
        $valueContainer->setSerialized($value->serialize());

        // If the value is a thunk value we also store its parent.
        if ($value->hasParentTask()) {
            $this->saveTask($value->getParentTask(), $bucketId);
            $valueContainer->setParentTaskid($this->getTaskContainerId($value->getParentTask()));
        }

        // We save the container and store the instance to container association.
        $valueContainer->save();
        $this->valueHashToValueContainerId[$value] = $valueContainer->getId();
    }

    /**
     * @throws SerializationException
     */
    public function getBucketContainerId(Bucket $bucket): int
    {
        if (!$this->bucketHashToObserverContainerId->contains($bucket)) {
            throw new SerializationException("Could not resolve container id of task: "
                . print_r($bucket, true));
        }

        return (int) $this->bucketHashToObserverContainerId[$bucket];
    }

    /**
     * @param $task Task
     * @throws SerializationException
     */
    protected function getTaskContainerId(Task $task): int
    {
        if (!$this->taskHashToTaskContainerId->contains($task)) {
            throw new SerializationException("Could not resolve container id of task: "
                . print_r($task, true));
        }

        return (int) $this->taskHashToTaskContainerId[$task];
    }

    /**
     * @throws SerializationException
     */
    protected function getValueContainerId(Value $value): int
    {
        if (!$this->valueHashToValueContainerId->contains($value)) {
            throw new SerializationException("Could not resolve container id of value: "
                . print_r($value, true));
        }

        return (int )$this->valueHashToValueContainerId[$value];
    }

    /**
     * @throws \ILIAS\BackgroundTasks\Exceptions\BucketNotFoundException
     */
    public function loadBucket(int $bucket_container_id): Bucket
    {
        if (isset(self::$buckets[$bucket_container_id])) {
            return self::$buckets[$bucket_container_id];
        }
        /** @var BucketContainer $bucketContainer */
        $bucketContainer = BucketContainer::find($bucket_container_id);
        if (!$bucketContainer) {
            throw new BucketNotFoundException("The requested bucket with container id $bucket_container_id could not be found in the database.");
        }
        $bucket = new BasicBucket();

        $bucket->setUserId($bucketContainer->getUserId());
        $bucket->setState($bucketContainer->getState());
        $bucket->setTitle($bucketContainer->getTitle());
        $bucket->setDescription($bucketContainer->getDescription());
        $bucket->setOverallPercentage($bucketContainer->getPercentage());
        $bucket->setLastHeartbeat($bucketContainer->getLastHeartbeat());
        $bucket->setTask($this->loadTask($bucketContainer->getRootTaskid(), $bucket, $bucketContainer));

        $this->bucketHashToObserverContainerId[$bucket] = $bucket_container_id;

        return $bucket;
    }

    /**
     * Recursively loads a task.
     * @param int             $taskContainerId The container ID to load.
     * @param Bucket          $bucket          Needed because we want to link the current task as
     *                                         soon as loaded.
     * @param BucketContainer $bucketContainer Needed because we need the current tasks container
     *                                         id for correct linking.
     */
    private function loadTask(int $taskContainerId, Bucket $bucket, BucketContainer $bucketContainer): \ILIAS\BackgroundTasks\Task
    {
        global $DIC;
        $factory = $DIC->backgroundTasks()->taskFactory();
        /** @var TaskContainer $taskContainer */
        $taskContainer = TaskContainer::find($taskContainerId);
        /** @noinspection PhpIncludeInspection */
        /** @var Task $task */
        $task = $factory->createTask($taskContainer->getClassName());

        // Added additional orderBy for the id to ensure that the items are returned in the right order.
        $valueToTasks = ValueToTaskContainer::where(['task_id' => $taskContainerId])
                                            ->orderBy('task_id')
                                            ->orderBy('position')
                                            ->orderBy('id')
                                            ->get();
        $inputs = [];
        foreach ($valueToTasks as $valueToTask) {
            $inputs[] = $this->loadValue($valueToTask->getValueId(), $bucket, $bucketContainer);
        }
        $task->setInput($inputs);

        if ($taskContainerId === $bucketContainer->getCurrentTaskid()) {
            $bucket->setCurrentTask($task);
        }

        $this->taskHashToTaskContainerId[$task] = $taskContainerId;

        return $task;
    }

    private function loadValue($valueContainerId, Bucket $bucket, BucketContainer $bucketContainer): \ILIAS\BackgroundTasks\Value
    {
        global $DIC;
        $factory = $DIC->backgroundTasks()->injector();

        /** @var ValueContainer $valueContainer */
        $valueContainer = ValueContainer::find($valueContainerId);
        /** @noinspection PhpIncludeInspection */

        /** @var Value $value */
        $value = $factory->createInstance($valueContainer->getClassName());

        $value->unserialize($valueContainer->getSerialized());
        if ($valueContainer->getHasParenttask() !== 0) {
            $value->setParentTask($this->loadTask($valueContainer->getParentTaskid(), $bucket, $bucketContainer));
        }

        $this->valueHashToValueContainerId[$value] = $valueContainerId;

        return $value;
    }

    public function deleteBucketById(int $bucket_id): void
    {
        $buckets = BucketContainer::where(['id' => $bucket_id])->get();
        array_map(function (BucketContainer $item): void {
            $item->delete();
        }, $buckets);

        /** @var TaskContainer[] $tasks */
        $tasks = TaskContainer::where(['bucket_id' => $bucket_id])->get();
        array_map(function (TaskContainer $item): void {
            $item->delete();
        }, $tasks);

        /** @var ValueContainer[] $values */
        $values = ValueContainer::where(['bucket_id' => $bucket_id])->get();
        array_map(function (ValueContainer $item): void {
            $item->delete();
        }, $values);

        /** @var ValueToTaskContainer[] $valueToTasks */
        $valueToTasks = ValueToTaskContainer::where(['bucket_id' => $bucket_id])->get();
        array_map(function (ValueToTaskContainer $item): void {
            $item->delete();
        }, $valueToTasks);
    }

    /**
     * @inheritdoc
     */
    public function deleteBucket(Bucket $bucket): void
    {
        $id = $this->getBucketContainerId($bucket);
        $this->deleteBucketById($id);
        $this->bucketHashToObserverContainerId->detach($bucket);
    }

    /**
     * @return \ILIAS\BackgroundTasks\Bucket[]
     */
    public function loadBuckets(array $bucket_container_ids): array
    {
        $buckets = [];
        foreach ($bucket_container_ids as $bucket_id) {
            try {
                $buckets[] = $this->loadBucket($bucket_id);
            } catch (\Throwable $t) {
                // there seem to be a problem with this container, we must delete it
                $this->deleteBucketById($bucket_id);
            }
        }

        return $buckets;
    }
}
