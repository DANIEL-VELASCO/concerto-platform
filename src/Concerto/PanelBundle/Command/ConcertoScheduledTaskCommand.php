<?php

namespace Concerto\PanelBundle\Command;

use Concerto\PanelBundle\Service\AdministrationService;
use Doctrine\Common\Persistence\ManagerRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Concerto\PanelBundle\Entity\ScheduledTask;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Input\ArrayInput;

abstract class ConcertoScheduledTaskCommand extends Command
{
    protected $administrationService;
    protected $administration;
    protected $doctrine;

    public function __construct(AdministrationService $administrationService, $administration, ManagerRegistry $doctrine)
    {
        $this->administrationService = $administrationService;
        $this->administration = $administration;
        $this->doctrine = $doctrine;

        parent::__construct();
    }

    protected function configure()
    {
        $this->addOption("task", null, InputOption::VALUE_OPTIONAL, "Task id", null);
        $this->addOption("cancel-pending-on-fail", null, InputOption::VALUE_NONE, "Cancels all other pending tasks when this task fails", null);
    }

    protected function check(&$error, &$code, InputInterface $input)
    {
        return true;
    }

    protected function getTaskResultFile(ScheduledTask $task)
    {
        return realpath(dirname(__FILE__) . "/../Resources/tasks") . "/concerto_task_" . $task->getId() . ".result";
    }

    protected function getTaskOutputFile(ScheduledTask $task)
    {
        return realpath(dirname(__FILE__) . "/../Resources/tasks") . "/concerto_task_" . $task->getId() . ".output";
    }

    abstract protected function executeTask(ScheduledTask $task, OutputInterface $output);

    abstract public function getTaskDescription(ScheduledTask $task);

    public function getTaskInfo(ScheduledTask $task, InputInterface $input)
    {
        $info = array(
            "task_output_path" => $this->getTaskOutputFile($task),
            "task_result_path" => $this->getTaskResultFile($task),
            "cancel_pending_on_fail" => $input->getOption("cancel-pending-on-fail")
        );
        return $info;
    }

    abstract public function getTaskType();

    protected function onBeforeTaskCreate(InputInterface $input, OutputInterface $output)
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln("checking...");
        if (!$this->check($error, $code, $input)) {
            $output->writeln($error);
            return $code;
        }
        $output->writeln("checks passed");

        $task_id = $input->getOption("task");

        $em = $this->doctrine->getManager();
        $tasksRepo = $em->getRepository("ConcertoPanelBundle:ScheduledTask");
        $task = null;
        if ($task_id) {
            //EXECUTE TASK

            $task = $tasksRepo->find($task_id);
            if (!$task) {
                $output->writeln("invalid task id!");
                return 1;
            }

            $task->setStatus(ScheduledTask::STATUS_ONGOING);
            $tasksRepo->save($task);

            $return_code = $this->executeTask($task, $output);
            if ($return_code !== 0) {
                $output->writeln("task #" . $task->getId() . " failed");
                return $return_code;
            }
            $output->writeln("task #" . $task->getId() . " finished successfully");
        } else {
            //SCHEDULE TASK

            $this->onBeforeTaskCreate($input, $output);

            $task = new ScheduledTask();
            $task->setType($this->getTaskType());
            $tasksRepo->save($task);
            $task->setInfo(json_encode($this->getTaskInfo($task, $input)));
            $task->setDescription($this->getTaskDescription($task));
            $tasksRepo->save($task);

            $output->writeln("task #" . $task->getId() . " scheduled");
        }
    }
}
