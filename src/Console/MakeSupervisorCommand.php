<?php

namespace Crm\Supervisor\Console;

use App\Services\Queue\AbstractQueueService;
use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;
use ReflectionClass;

class MakeSupervisorCommand extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:supervisor {--name=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'create supervisor ini file from queue service';

    /**
     * Queue Service List
     * @var array
     */
    protected $queueService = [];

    /**
     * Supervisor ini save path
     * @var string
     */
    protected $supervisorPath = '';


    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->load();
        $this->setSupervisorPath();
        foreach ($this->getQueueService() as $queueService) {
            $serviceName = $queueService['service_name'];
            $queueName = $queueService['queue_name'];
            $queueNum = $queueService['queue_num'];
            $logPath = $this->getLogPath($serviceName);
            for ($i = 1; $i <= $queueNum; $i++) {
                $programName = $serviceName.'Queue'.$i;
                $this->files->put($this->getFile($serviceName, $queueName.'_'.$i),
                    $this->buildSupervisor($programName, base_path(), $queueName.':queue_'.$i,
                        $logPath.$programName.'_stderr.log',
                        $logPath.$programName.'_stdout.log'));
            }
        }
    }

    protected function load()
    {
        $paths = app_path('Services/Queue');
        $namespace = $this->rootNamespace();
        foreach ((new Finder)->in($paths)->files() as $service) {
            $service = $namespace.str_replace(
                    ['/', '.php'],
                    ['\\', ''],
                    Str::after($service->getPathname(), app_path().DIRECTORY_SEPARATOR)
                );
            $ReflectionService = new ReflectionClass($service);
            if (is_subclass_of($service, AbstractQueueService::class) && !$ReflectionService->isAbstract()) {
                $queueNum = $service::setQueueNum();
                $queueServiceName = Str::before($ReflectionService->getShortName(), 'QueueService');
                $this->queueService[$queueServiceName] = [
                    'service_name' => Str::camel($queueServiceName),
                    'queue_name' => $service::getServiceName(),
                    'queue_num' => $queueNum,
                ];
            }
        }
    }

    protected function setSupervisorPath()
    {
        $supervisorPath = base_path('supervisor');
        if ($this->files->exists($supervisorPath)) {
            $this->files->cleanDirectory($supervisorPath);
        } else {
            $this->files->makeDirectory($supervisorPath);
        }
        $this->supervisorPath = $supervisorPath;
    }

    protected function getQueueService()
    {
        $serviceName = $this->getNameInput();
        if ($serviceName) {
            return [$this->queueService[$serviceName]];
        }
        return $this->queueService;

    }

    protected function getNameInput()
    {
        return trim($this->option('name'));
    }

    protected function getLogPath($name)
    {
        $logPath = storage_path('logs/supervisor/'.$name.'/');
        if (!$this->files->exists($logPath)) {
            $this->files->makeDirectory($logPath,0755,true);
        }
        return $logPath;
    }

    protected function getStub()
    {
        return __DIR__.'/Stubs/Supervisor.stub';
    }

    protected function getFile($path, $file)
    {
        $path = $this->supervisorPath.'/'.$path.'/';
        if (!$this->files->exists($path)) {
            $this->files->makeDirectory($path);
        }
        return $path.$file.'.ini';
    }

    protected function buildSupervisor($programName, $basePath, $queueName, $stderrLog, $stdoutLog)
    {
        $stub = $this->files->get($this->getStub());
        return $this->replaceStub($stub, $programName, $basePath, $queueName, $stderrLog, $stdoutLog);
    }

    protected function replaceStub($stub, $programName, $basePath, $queueName, $stderrLog, $stdoutLog)
    {
        return str_replace(
            ['ProgramName', 'BasePath', 'QueueName', 'StderrLog', 'StdoutLog'],
            [$programName, $basePath, $queueName, $stderrLog, $stdoutLog],
            $stub
        );
    }
}
