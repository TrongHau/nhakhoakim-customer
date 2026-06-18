<?php

namespace App\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Str;

class MakeRepositoryCommand extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:repository {name : The repository class that you want to create}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new repository class';

    /**
     * @var string
     */
    protected $type = 'Repository';

    /**
     * Execute the console command.
     *
     * @return bool|null
     *
     * @throws FileNotFoundException
     */
    public function handle(): ?bool
    {
        parent::handle();
        return true;
    }
    /**
     * @param string $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace . '\Repositories';
    }

    /**
     * @return string
     */
    protected function getStub(): string
    {
        return __DIR__.'/stubs/repository.stub';
    }

    /**
     * Replace the namespace for the given stub.
     *
     * @param  string  $stub
     * @param  string  $name
     * @return $this
     */
    protected function replaceNamespace(&$stub, $name)
    {
        parent::replaceNamespace($stub, $name);

        $classNameRepo = $this->getNameRepository();

        $stub = str_replace(['DummyModel', '{{ DummyModel }}', '{{DummyModel}}'], ucfirst($classNameRepo), $stub);

        return $this;
    }

    /**
     * Create a controller for the model.
     *
     * @return void
     */
    protected function createInterface()
    {
        $interface = $this->getNameRepository();


        $this->call('make:repository_interface', [
            'name' => "{$interface}Interface",
        ]);
    }

    /**
     * @return string
     */
    private function getNameRepository(): string
    {
        return Str::studly(Str::replaceFirst('Repository','',$this->argument('name')));
    }
}
