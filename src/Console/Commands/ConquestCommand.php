<?php

namespace Conquest\Assemble\Console\Commands;

use Conquest\Assemble\Concerns\HasMethods;
use Illuminate\Support\Str;
use Conquest\Assemble\Concerns\HasNames;
use Illuminate\Console\GeneratorCommand;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\text;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;

use Conquest\Assemble\Concerns\IsInertiable;
use Conquest\Assemble\Concerns\ResolvesStubPath;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'conquest')]
class ConquestCommand extends GeneratorCommand
{
    use HasMethods;
    use IsInertiable;
    use ResolvesStubPath;

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Conquest';

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'conquest';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Accelerate the creation of CRUD applications.';

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return $this->resolveStubPath('/stubs/conquest.controller.stub');
    }

    /**
     * Get the desired class name from the input.
     *
     * @return string
     */
    protected function getMethodInput()
    {
        $name = trim($this->argument('method'));

        return str($name)->lower()->ucfirst()->toString();
    }

    protected function getArguments()
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the controller to generate.'],
            ['method', InputArgument::OPTIONAL, 'The method of the controller to generate.'],
        ];
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['force', null, InputOption::VALUE_NONE, 'Create the class(es) even if they already exist'],

            ['modal', 'M', InputOption::VALUE_NONE, 'Create a new modal for the endpoint'],
            ['page', 'P', InputOption::VALUE_NONE, 'Create a new page for the endpoint'],
            ['form', 'F', InputOption::VALUE_NONE, 'Indicates whether the generated page or modal should be a form'],

            ['model', 'm', InputOption::VALUE_NONE, 'Create a new model'],
            ['policy', 'p', InputOption::VALUE_NONE, 'Create a new policy for the model'],
            ['migration', 'i', InputOption::VALUE_NONE, 'Create a new migration file for the model'],
            ['seed', 's', InputOption::VALUE_NONE, 'Create a new seeder for the model'],
            ['factory', 'f', InputOption::VALUE_NONE, 'Create a new factory for the model'],
            ['resource', 'r', InputOption::VALUE_NONE, 'Create a new resource for the model'],

            ['crud', 'c', InputOption::VALUE_NONE, 'Generate endpoints for CRUD operations'],
            ['route', 'R', InputOption::VALUE_NONE, 'Indicates whether the generated controller should be added to the route.php or specified route file'],
            ['file', 'W', InputOption::VALUE_OPTIONAL, 'Supply the file to create the route routes in'],
            ['all', 'a', InputOption::VALUE_NONE, 'Generate a model, policy, migration, seeder, factory, resource, pages/modals and 8 endpoints which are added to the routes'],
        ];
    }

    /**
     * Execute the console command.
     *
     * @return bool|null
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function handle()
    {
        if ($this->isReservedName($this->getNameInput())) {
            $this->components->error('The name "'.$this->getNameInput().'" is reserved by PHP.');
            return false;
        }

        if ($this->option('all')) {
            $this->input->setOption('model', true);
            $this->input->setOption('factory', true);
            $this->input->setOption('seed', true);
            $this->input->setOption('migration', true);
            $this->input->setOption('policy', true);
            $this->input->setOption('resource', true);
            $this->input->setOption('crud', true);
            $this->input->setOption('route', true);
        }

        $name = $this->getPureClassName($this->getNameInput());
        $method = $this->getMethodInput();
        
        if (!$method && !$this->isValidMethod($method) && !$this->option('crud')) {
            $this->components->warn('You have not supplied a valid method.');
            if (! confirm('Are you sure you want to proceed? This will limit some of the functionality available.')) {
                return false;
            }
        }

        if ($this->option('model')) {
            $this->call('make:model', [
                'name' => $this->getBase($name),
                '--force' => $this->option('force'),
                '--factory' => $this->option('factory'),
                '--seed' => $this->option('seed'),
                '--migration' => $this->option('migration'),
                '--policy' => $this->option('policy'),
            ]);
        }

        if ($this->option('resource')) {
            $this->call('make:resource', [
                'name' => $name,
                '--force' => $this->option('force'),
            ]);
        }

        if ($this->option('crud')) {
            foreach ($this->methods as $method) {
                if (! $this->createConquest($name, $method)) {
                    return false;
                }
            }
        } else {
            if (! $this->createConquest($name, $method)) {
                return false;
            }
        }

        $this->components->success('All Conquest components created successfully.');

        return true;
    }

    /**
     * Prompt for missing input arguments using the returned questions.
     *
     * @return array
     */
    protected function promptForMissingArgumentsUsing()
    {
        return [
            'name' => [
                'What argument should be used as the generator for this conquest command?',
                'E.g. User',
            ],
            'method' => [
                'What methods should be used for this conquest command? (Select multiple if needed)',
                'Available options: index, create, store, show, edit, update, destroy',
                fn () => select(
                    'Select methods:',
                    ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']
                ),
            ],
        ];
    }

    /**
     * Build the class with the given name.
     *
     * @param  string  $name
     * @param  ?string  $method
     * @return string
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    protected function buildConquestClass($name, $method)
    {
        $stub = $this->files->get($this->getStub());

        return $this->replaceNamespace($stub, $this->getController($name, $method)->prepend('App/Http/Controllers/')->explode('/')->slice(0, -1)->implode('\\'))
            ->replaceRequest($stub, $this->getRequest($name, $method)->prepend('App/Http/Requests/')->replace('/', '\\'))
            ->replaceInertia($stub, $method)
            ->replaceModel($stub, str($this->getBase($name))->prepend('App/Models/')->replace('/', '\\'))
            ->replaceInvoke($stub, $name, $method)
            ->replaceResponse($stub, $name, $method)
            ->replaceEmptyLines($stub)
            ->replaceClass($stub, $this->getBase($this->getController($name, $method)));
    }


    /**
     * Replace the request import to use the generated request.
     *
     * @param  string  $stub
     * @return static
     */
    protected function replaceRequest(&$stub, $name)
    {
        $stub = str_replace(['DummyRequest', '{{ request }}', '{{request}}'], $name, $stub);

        return $this;
    }

    /**
     * Import Inertia if needed.
     *
     * @param  string  $stub
     * @param  string  $method
     * @return static
     */
    protected function replaceInertia(&$stub, $method)
    {
        if (! $this->isNotInertiable($method)) {
            $stub = str_replace(['{{ inertia }}', '{{inertia}}'], 'use Inertia\Inertia;', $stub);
        } else {
            $stub = str_replace(['{{ inertia }}', '{{inertia}}'], '', $stub);
        }

        return $this;
    }

    /**
     * Import the model if needed.
     *
     * @param  string  $stub
     * @return static
     */
    protected function replaceModel(&$stub, $name)
    {
        if ($this->option('model')) {
            $stub = str_replace(['{{ model }}', '{{model}}'], 'use '.$name.';', $stub);
        } else {
            $stub = str_replace(['{{ model }}', '{{model}}'], '', $stub);
        }

        return $this;
    }

    /**
     * Generate the invoke method signature for the controller.
     *
     * @param  string  $stub
     * @param  string  $name
     * @param  ?string  $method
     * @return static
     */
    protected function replaceInvoke(&$stub, $name, $method)
    {
        $request = $this->getBase($this->getRequest($name, $method));

        if ($this->option('model')) {
            $model = $this->getBase($name);
            $stub = str_replace(['{{ invoke }}', '{{invoke}}'], sprintf('public function __invoke(%s $request, %s $%s)', $request, $model, str($model)->camel()), $stub);
        } else {
            $stub = str_replace(['{{ invoke }}', '{{invoke}}'], sprintf('public function __invoke(%s $request)', $request), $stub);
        }

        return $this;
    }

    /**
     * Generate the response for the controller.
     *
     * @param  string  $stub
     * @param  string  $name
     * @param  ?string  $method
     * @return static
     */
    protected function replaceResponse(&$stub, $name, $method)
    {
        $model = $this->getBase($name);
        $props = $this->option('model') ? sprintf("'%s' => $%s", $c = str($model)->camel(), $c) : '';
        $resource = str($name)->append($method);

        if ($this->isPage($method) || ($this->option('page') && ! $this->isNotInertiable($method))) {
            $stub = str_replace(['{{ response }}', '{{response}}'], sprintf("return Inertia::render('%s', [\n\t\t\t%s\n\t\t]);", $resource, $props), $stub);
        } elseif ($this->isModal($method) || ($this->option('modal') && ! $this->isNotInertiable($method))) {
            $stub = str_replace(['{{ response }}', '{{response}}'], sprintf("return Inertia::modal('%s', [\n\t\t\t%s\n\t\t])->baseRoute(%s);", $resource, $props, config('assemble.base_route')), $stub);
        } else {
            $stub = str_replace(['{{ response }}', '{{response}}'], 'return back();', $stub);
        }

        return $this;
    }

    /**
     * Replace empty lines with a single line.
     *
     * @param  string  $stub
     * @return static
     */
    protected function replaceEmptyLines(&$stub)
    {
        $stub = str_replace("\r\n\r\n\r\n", "\r\n\r\n", $stub);

        return $this;
    }

    protected function getRequest($name, $method)
    {
        return str($name)->append($method)->append('Request');
    }

    protected function getController($name, $method)
    {
        return str($name)->append($method)->append('Controller');
    }

    /**
     * Create a Conquest controller and request pair, and any Javascript resources.
     *
     * @param  string  $name
     * @param  ?string  $method
     */
    protected function createConquest($name, $method)
    {
        $controller = $this->getController($name, $method);
        $controllerPath = $controller->prepend('Http/Controllers/');
        $path = $this->getPath($controllerPath);

        if ((! $this->hasOption('force') ||
             ! $this->option('force')) &&
             $this->alreadyExists($controllerPath)) {
            $this->components->error($this->type.' already exists.');

            return false;
        }

        $this->makeDirectory($path);
        $this->files->put($path, $this->sortImports($this->buildConquestClass($name, $method)));

        if (windows_os()) {
            $path = str_replace('/', '\\', $path);
        }

        $this->components->info(sprintf('Controller [%s] created successfully.', $path));

        $this->call('make:request', [
            'name' => $this->getRequest($name, $method),
            '--force' => $this->option('force'),
        ]);

        $this->call('make:route', [
            'controller' => $controller,
            '--model' => $this->option('model'),
            '--file' => $this->option('file'),
            '--class' => $this->option('model'),
        ]);

        if ($this->option('model') && $this->option('policy')) {
            // Overwrite the request authorize with the policy
        }

        $resource = str($name)->append($method);

        return match (true) {
            $this->option('page') && ! $this->isNotInertiable($method) => $this->call('make:page', [
                'name' => $resource,
                '--force' => $this->option('force'),
                '--form' => $this->isForm($method),
            ]),
            $this->option('modal') && ! $this->isNotInertiable($method) => $this->call('make:modal', [
                'name' => $resource,
                '--force' => $this->option('force'),
                '--form' => $this->isForm($method),
            ]),
            $this->isPage($method) => $this->call('make:page', [
                'name' => $resource,
                '--force' => $this->option('force'),
                '--form' => $this->isForm($method),
            ]),
            $this->isModal($method) => $this->call('make:modal', [
                'name' => $resource,
                '--force' => $this->option('force'),
                '--form' => $this->isForm($method),
            ]),
            default => true,
        };
    }

    /**
     * Interact further with the user if they were prompted for missing arguments.
     *
     * @return void
     */
    protected function afterPromptingForMissingArguments(InputInterface $input, OutputInterface $output)
    {
        if ($this->isReservedName($this->getNameInput()) || $this->didReceiveOptions($input)) {
            return;
        }

        collect(multiselect('Would you like any of the following?', [
            'all' => 'All',
            'crud' => 'CRUD',
            'force' => 'Force creation',
            'model' => 'Model',
            'route' => 'Web Route(s)',
        ]))->each(fn ($option) => $input->setOption($option, true));

        if ($input->getOption('all')) {
            return;
        }        

        if ($input->getOption('model')) {
            collect(multiselect('Would you like to additionally create any of the following for the given model?', [
                'factory' => 'Factory',
                'migration' => 'Migration',
                'policy' => 'Policy',
                'resource' => 'Resource',
                'seed' => 'Seeder',
            ]))->each(fn ($option) => $input->setOption($option, true));
        }

        if (true) {
            collect(multiselect('Would you like change the behaviour of the generated Javascript resource?', [
                'page' => 'Page',
                'modal' => 'Modal',
                'form' => 'As form',
            ]))->each(fn ($option) => $input->setOption($option, true));
        }

        if ($this->option('route')) {
            $route = text('What route file would you like to add the generated routes to?');
            $input->setOption('file', $route);
        }
    }
}
