<?php

namespace Concerto\PanelBundle\Service;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpKernel\KernelInterface;

class GitService
{
    const GIT_HISTORY_ROWS_LIMIT = 10;

    private $adminService;
    private $kernel;

    public function __construct(KernelInterface $kernel, AdministrationService $adminService)
    {
        $this->kernel = $kernel;
        $this->adminService = $adminService;
    }

    public function getGitExecPath()
    {
        return $this->adminService->getSettingValue("git_exec_path");
    }

    public function getGitRepoPath()
    {
        return realpath(__DIR__ . "/../Resources") . "/git";
    }

    public function isEnabled()
    {
        return $this->adminService->getSettingValue("git_enabled") == 1;
    }

    public function getUrl()
    {
        return $this->adminService->getSettingValue("git_url");
    }

    public function getLogin()
    {
        return $this->adminService->getSettingValue("git_login");
    }

    public function getPassword()
    {
        return $this->adminService->getSettingValue("git_password");
    }

    public function getBranch()
    {
        return $this->adminService->getSettingValue("git_branch");
    }

    private function saveGitSettings($url, $branch, $login, $password)
    {
        $gitSettings = [];
        if ($url !== null) $gitSettings["git_url"] = $url;
        if ($branch !== null) $gitSettings["git_branch"] = $branch;
        if ($login !== null) $gitSettings["git_login"] = $login;
        if ($password !== null) $gitSettings["git_password"] = $password;

        if (!empty($gitSettings)) {
            $gitSettings["git_enabled"] = 1;

            $this->adminService->setSettings($gitSettings, true);
        }
    }

    public function disableGit()
    {
        $this->adminService->setSettings(array(
            "git_enabled" => 0
        ), true);
    }

    public function getBehindNum(&$errorMessages = null)
    {
        $app = new Application($this->kernel);
        $app->setAutoExit(false);
        $command = $app->find("concerto:git:position");
        $arguments = [
            "command" => $command->getName(),
            "direction" => "behind"
        ];
        $in = new ArrayInput($arguments);
        $out = new BufferedOutput();
        $returnCode = $app->run($in, $out);
        $output = $out->fetch();
        if ($returnCode === 0) {
            return (int)$output;
        }
        $errorMessages[] = $output;
        return false;
    }

    public function getAheadNum(&$errorMessages = null)
    {
        $app = new Application($this->kernel);
        $app->setAutoExit(false);
        $command = $app->find("concerto:git:position");
        $arguments = [
            "command" => $command->getName(),
            "direction" => "ahead"
        ];
        $in = new ArrayInput($arguments);
        $out = new BufferedOutput();
        $returnCode = $app->run($in, $out);
        $output .= $out->fetch();
        if ($returnCode === 0) {
            return (int)$output;
        }
        $errorMessages[] = $output;
        return false;
    }

    public function getHistory(&$errorMessages = null)
    {
        $app = new Application($this->kernel);
        $app->setAutoExit(false);
        $command = $app->find("concerto:git:history");
        $arguments = [
            "command" => $command->getName()
        ];
        $in = new ArrayInput($arguments);
        $out = new BufferedOutput();
        $returnCode = $app->run($in, $out);
        $output = $out->fetch();
        if ($returnCode === 0) {
            return $this->parseHistory($output);
        }
        $errorMessages[] = $output;
        return false;
    }

    private function parseHistory($history)
    {
        $result = [];
        $lines = explode("\n", $history);
        $i = 0;
        foreach ($lines as $line) {
            if (trim($line) === "") continue;
            $i++;
            preg_match("/(.*) \|\|\| (.*) \|\|\| (.*) \|\|\| (.*) \|\|\| (.*)/", $line, $entries);
            array_push($result, [
                "sha" => $entries[1],
                "committer" => $entries[2],
                "timeAgo" => $entries[3],
                "subject" => $entries[4],
                "ref" => $entries[5]
            ]);
            if ($i == self::GIT_HISTORY_ROWS_LIMIT) break;
        }
        return $result;
    }

    public function getDiff($sha, &$errorMessages = null)
    {
        $app = new Application($this->kernel);
        $app->setAutoExit(false);
        $command = $app->find("concerto:git:diff");
        $arguments = [
            "command" => $command->getName(),
            "--sha" => $sha
        ];
        $in = new ArrayInput($arguments);
        $out = new BufferedOutput();
        $returnCode = $app->run($in, $out);
        $output = $out->fetch();
        if ($returnCode === 0) {
            return $output;
        }
        $errorMessages[] = $output;
        return false;
    }

    public function fetch(&$errorMessages = null)
    {
        $app = new Application($this->kernel);
        $app->setAutoExit(false);
        $command = $app->find("concerto:git:fetch");
        $arguments = ["command" => $command->getName()];
        $in = new ArrayInput($arguments);
        $out = new BufferedOutput();
        $returnCode = $app->run($in, $out);
        if ($returnCode === 0) {
            return true;
        }
        $errorMessages[] = $out->fetch();
        return false;
    }

    private function exportWorkingCopy($exportInstructions, &$output = null, &$errorMessages = null)
    {
        if ($exportInstructions === null) $exportInstructions = $this->adminService->getSettingValue("content_export_options");

        $app = new Application($this->kernel);
        $app->setAutoExit(false);
        $command = $app->find("concerto:content:export");
        $arguments = [
            "command" => $command->getName(),
            "output" => $this->getGitRepoPath(),
            "--instructions" => $exportInstructions,
            "--sc" => true,
            "-y" => true
        ];
        $in = new ArrayInput($arguments);
        $out = new BufferedOutput();
        $returnCode = $app->run($in, $out);
        $output .= $out->fetch();
        if ($returnCode === 0) {
            return true;
        }
        $errorMessages[] = $out->fetch();
        return false;
    }

    private function add(&$output = null, &$errorMessages = null)
    {
        $app = new Application($this->kernel);
        $app->setAutoExit(false);
        $command = $app->find("concerto:git:add");
        $arguments = ["command" => $command->getName()];
        $in = new ArrayInput($arguments);
        $out = new BufferedOutput();
        $returnCode = $app->run($in, $out);
        $output .= $out->fetch();
        if ($returnCode === 0) {
            return true;
        }
        $errorMessages[] = $out->fetch();
        return false;
    }

    public function commit($message, &$output, &$errorMessages = null)
    {
        if (!$this->adminService->canDoRiskyGitActions()) {
            $errorMessages[] = "git.locked";
            return false;
        }

        $user = $this->adminService->getAuthorizedUser();

        $app = new Application($this->kernel);
        $app->setAutoExit(false);
        $command = $app->find("concerto:git:commit");
        $arguments = [
            "command" => $command->getName(),
            "message" => $message,
            "username" => $user->getUsername(),
            "email" => $user->getEmail()
        ];
        $in = new ArrayInput($arguments);
        $out = new BufferedOutput();
        $returnCode = $app->run($in, $out);
        $output .= $out->fetch();
        if ($returnCode === 0) {
            return true;
        }
        $errorMessages[] = $output;
        return false;
    }

    public function getStatus($exportInstructions, &$errorMessages = null)
    {
        if ($this->fetch($errorMessages) === false) {
            $errorMessages[] = "git.fetch_failed";
            return false;
        }

        $behind = $this->getBehindNum($errorMessages);
        if ($behind === false) {
            $errorMessages[] = "git.behind_num_failed";
            return false;
        }

        $ahead = $this->getAheadNum($errorMessages);
        if ($ahead === false) {
            $errorMessages[] = "git.ahead_num_failed";
            return false;
        }

        $history = $this->getHistory($errorMessages);
        if ($history === false) {
            $errorMessages[] = "git.history_failed";
            return false;
        }

        $diff = $this->getDiff(null, $errorMessages);
        if ($diff === false) {
            $errorMessages[] = "git.diff_failed";
            return false;
        }

        return $status = [
            "behind" => $behind,
            "ahead" => $ahead,
            "history" => $history,
            "diff" => $diff
        ];
    }

    public function reset($instructions, &$output = null, &$errorMessages = null)
    {
        if (!$this->adminService->canDoRiskyGitActions()) {
            $errorMessages[] = "git.locked";
            return false;
        }

        if (!$this->gitReset($output, $errorMessages)) {
            $errorMessages[] = "git.reset_failed";
            return false;
        }

        if (!$this->importWorkingCopy($instructions, $output, $errorMessages)) {
            $errorMessages[] = "git.import_failed";
            return false;
        }
        return true;
    }

    private function gitReset(&$output = null, &$errorMessages = null)
    {
        $app = new Application($this->kernel);
        $app->setAutoExit(false);
        $command = $app->find("concerto:git:reset");
        $arguments = [
            "command" => $command->getName()
        ];
        $in = new ArrayInput($arguments);
        $out = new BufferedOutput();
        $returnCode = $app->run($in, $out);
        $output .= $out->fetch();
        if ($returnCode === 0) {
            return true;
        }
        $errorMessages[] = $out->fetch();
        return false;
    }

    private function importWorkingCopy($instructions, &$output, &$errorMessages = null)
    {
        if ($instructions === null) $instructions = $this->adminService->getSettingValue("content_export_options");

        $app = new Application($this->kernel);
        $app->setAutoExit(false);
        $command = $app->find("concerto:content:import");
        $arguments = [
            "command" => $command->getName(),
            "input" => $this->getGitRepoPath(),
            "--instructions" => $instructions,
            "--sc" => true
        ];
        $in = new ArrayInput($arguments);
        $out = new BufferedOutput();
        $returnCode = $app->run($in, $out);
        $output .= $out->fetch();
        if ($returnCode === 0) {
            return true;
        }
        $errorMessages[] = $out->fetch();
        return false;
    }

    public function push(&$output, &$errorMessages = null)
    {
        $app = new Application($this->kernel);
        $app->setAutoExit(false);
        $command = $app->find("concerto:git:push");
        $arguments = ["command" => $command->getName()];
        $in = new ArrayInput($arguments);
        $out = new BufferedOutput();
        $returnCode = $app->run($in, $out);
        $output .= $out->fetch();
        if ($returnCode === 0) {
            return true;
        }
        $errorMessages[] = $out->fetch();
        return false;
    }

    public function pull($username, $email, $instructions, &$output, &$errorMessages = null)
    {
        if (!$this->gitPull($username, $email, $output, $errorMessages)) {
            $errorMessages[] = "git.pull_failed";
            return false;
        }

        if (!$this->importWorkingCopy($instructions, $output, $errorMessages)) {
            $errorMessages[] = "git.import_failed";
            return false;
        }
        return true;
    }

    public function gitPull($username, $email, &$output = null, &$errorMessages = null)
    {
        $app = new Application($this->kernel);
        $app->setAutoExit(false);
        $command = $app->find("concerto:git:pull");
        $arguments = [
            "command" => $command->getName(),
            "username" => $username,
            "email" => $email
        ];
        $in = new ArrayInput($arguments);
        $out = new BufferedOutput();
        $returnCode = $app->run($in, $out);
        $output .= $out->fetch();
        if ($returnCode === 0) {
            return true;
        }
        $errorMessages[] = $out->fetch();
        return false;
    }

    public function scheduleTaskGitPull($exportInstructions, &$output = null, &$errors = null)
    {
        if ($this->adminService->isTaskScheduled()) {
            $errors[] = "tasks.already_scheduled";
            return false;
        }
        if (!$this->adminService->canDoRiskyGitActions()) {
            $errors[] = "git.locked";
            return false;
        }

        $user = $this->adminService->getAuthorizedUser();

        $app = new Application($this->kernel);
        $app->setAutoExit(false);
        $in = new ArrayInput(array(
            "command" => "concerto:task:git:pull",
            "username" => $user->getUsername(),
            "email" => $user->getEmail(),
            "--instructions" => $exportInstructions
        ));
        $out = new BufferedOutput();
        $returnCode = $app->run($in, $out);
        $output .= $out->fetch();
        return $returnCode === 0;
    }

    public function scheduleTaskGitEnable($url, $branch, $login, $password, $instantRun = false, &$output = null, &$errors = null)
    {
        if ($this->adminService->isTaskScheduled()) {
            $errors[] = "tasks.already_scheduled";
            return false;
        }
        if (!$this->adminService->canDoRiskyGitActions()) {
            $errors[] = "git.locked";
            return false;
        }

        $this->saveGitSettings($url, $branch, $login, $password);

        $app = new Application($this->kernel);
        $app->setAutoExit(false);
        $in = new ArrayInput(array(
            "command" => "concerto:task:git:enable",
            "--instant-run" => $instantRun
        ));
        $out = new BufferedOutput();
        $returnCode = $app->run($in, $out);
        $output .= $out->fetch();
        return $returnCode === 0;
    }

    public function scheduleTaskGitUpdate($exportInstructions, &$output = null, &$errors = null)
    {
        if ($this->adminService->isTaskScheduled()) {
            $errors[] = "tasks.already_scheduled";
            return false;
        }
        if (!$this->adminService->canDoRiskyGitActions()) {
            $errors[] = "git.locked";
            return false;
        }

        $app = new Application($this->kernel);
        $app->setAutoExit(false);
        $in = new ArrayInput(array(
            "command" => "concerto:task:git:update",
            "--instructions" => $exportInstructions
        ));
        $out = new BufferedOutput();
        $returnCode = $app->run($in, $out);
        $output .= $out->fetch();
        return $returnCode === 0;
    }

    public function update($instructions, &$output = null, &$errorMessages = null)
    {
        if ($this->exportWorkingCopy($instructions, $output, $errorMessages) === false) {
            $errorMessages[] = "git.refresh_failed";
            return false;
        }

        if ($this->add($output, $errorMessages) === false) {
            $errorMessages[] = "git.add_failed";
            return false;
        }

        return true;
    }
}