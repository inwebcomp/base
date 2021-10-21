<?php

namespace InWeb\Base\Console;

use App\Models\Navigation;
use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use InWeb\Base\Contracts\Cacheable;
use InWeb\Base\Entity;
use InWeb\Base\Traits\ClearsRelatedModelCache;
use InWeb\Base\Traits\Positionable;
use InWeb\Base\Traits\Translatable;
use InWeb\Base\Traits\WithUID;
use InWeb\Media\Images\WithImages;
use Spatie\EloquentSortable\Sortable;
use Symfony\Component\Process\Process;

class ModelCommand extends Command
{
    use RenamesStubs;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'imake:model {name} 
                                        {--f|field=*} 
                                        {--F|tfield=*} 
                                        {--t|translatable} 
                                        {--s|sluggable}
                                        {--i|images} 
                                        {--p|positionable} 
                                        {--c|cacheable}
                                        {--m|migration}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Create a base entity";

    /**
     * @var array
     */
    protected $use = [];
    /**
     * @var array
     */
    private $interfaces = [];
    /**
     * @var string
     */
    private $body = '';
    /**
     * @var string
     */
    private $translatableBody = '';

    /**
     * @var array
     */
    private $traits = [];
    /**
     * @var array
     */
    private $translatableTraits = [];
    /**
     * @var array
     */
    private $fields = [];
    /**
     * @var array
     */
    private $translatableFields = [];
    /**
     * @var array
     */
    private $casts = [];

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        if (! $this->hasValidNameArgument()) {
            return;
        }

        $file = $this->modelsPath() . '/' . $this->modelClass() . '.stub';

        (new Filesystem)->copy(
            __DIR__ . '/stubs/Entity.stub',
            $file
        );

        $this->initFields();

        $this->use[] = Entity::class;

        $this->makeImages();
        $this->makeTranslatable();
        $this->makeCacheable();
        $this->makePositionable();
        $this->makeUID();
        $this->makeStatus();
        $this->makeMigration();

        // Entity.php replacements...
        $this->replace('{{ body }}', $this->body, $file);
        $this->replace('{{ namespace }}', $this->modelNamespace(), $file);
        $this->replace('{{ class }}', $this->modelClass(), $file);
        $this->replace('{{ name }}', $this->modelName(), $file);
        $this->replace('{{ name_plural }}', Str::plural($this->modelName()), $file);

        $this->replace('{{ interfaces }}', $this->getInterfaces(), $file);
        $this->replace('{{ traits }}', $this->getTraits(), $file);
        $this->replace('{{ use }}', $this->formatUse(), $file);
        $this->replace('{{ casts }}', $this->getCasts(), $file);

        $this->replace('{{ translation_class }}', $this->translationsNamespace() . '\\' . $this->translationClass(), $file);
        $this->replace('{{ translatable_fields }}', $this->getTranslatableFields(), $file);

        // Rename the stubs with the proper file extensions...
        $this->renameStubs();
    }

    /**
     * Get the array of stubs that need PHP file extensions.
     *
     * @return array
     */
    protected function stubsToRename()
    {
        return [
            $this->modelsPath() . '/' . $this->modelClass() . '.stub',
            $this->translationsPath() . '/' . $this->translationClass() . '.stub',
            $this->migrationsPath() . '/' . $this->migrationName() . '.stub',
        ];
    }

    /**
     * Run the given command as a process.
     *
     * @param string $command
     * @param string $path
     * @return void
     */
    protected function executeCommand($command, $path)
    {
        $process = (Process::fromShellCommandline($command, $path))->setTimeout(null);

        if ('\\' !== DIRECTORY_SEPARATOR && file_exists('/dev/tty') && is_readable('/dev/tty')) {
            $process->setTty(true);
        }

        $process->run(function ($type, $line) {
            $this->output->write($line);
        });
    }

    /**
     * Replace the given string in the given file.
     *
     * @param string $search
     * @param string $replace
     * @param string $path
     * @return void
     */
    protected function replace($search, $replace, $path)
    {
        file_put_contents($path, str_replace($search, $replace, file_get_contents($path)));
    }

    /**
     * Get the path to the tool.
     *
     * @return string
     */
    protected function modelsPath()
    {
        return base_path('app/Models');
    }

    /**
     * Get the relative path to the tool.
     *
     * @return string
     */
    protected function relativeModelsPath()
    {
        return 'app/Models';
    }

    /**
     * Get the path to the tool.
     *
     * @return string
     */
    protected function translationsPath()
    {
        return base_path('app/Translations');
    }

    /**
     * Get the relative path to the tool.
     *
     * @return string
     */
    protected function relativeTranslationsPath()
    {
        return 'app/Translations';
    }

    /**
     * Get the tool's namespace.
     *
     * @return string
     */
    protected function modelNamespace()
    {
        return Str::studly(str_replace('/', '\\', $this->modelsPath())) . $this->modelClass();
    }

    /**
     * Get the tool's escaped namespace.
     *
     * @return string
     */
    protected function escapedModelNamespace()
    {
        return str_replace('\\', '\\\\', $this->modelNamespace());
    }

    /**
     * Get the tool's class name.
     *
     * @return string
     */
    protected function modelClass()
    {
        return Str::studly($this->modelName());
    }

    /**
     * Get the tool's class name.
     *
     * @return string
     */
    protected function translationClass()
    {
        return Str::studly($this->modelName() . 'Translation');
    }

    public function translationsNamespace()
    {
        return 'App\Translations';
    }

    /**
     * Get the tool's class name.
     *
     * @return string
     */
    protected function getTranslatableFields()
    {
        return implode(', ', array_map(function ($value) {
            return "'" . $value . "'";
        }, $this->translatableFields));
    }

    /**
     * Get the "title" name of the tool.
     *
     * @return string
     */
    protected function modelTitle()
    {
        $name = $this->modelName();

        if ($name == 'inweb')
            return 'InWeb';

        return Str::title(str_replace('-', ' ', $name));
    }

    /**
     * Get the tool's base name.
     *
     * @return string
     */
    protected function modelName()
    {
        return Str::snake($this->argument('name'));
    }

    private function hasValidNameArgument()
    {
        return $this->argument('name');
    }

    private function getInterfaces()
    {
        if (! count($this->interfaces))
            return '';

        return 'implements ' . implode(", ", $this->interfaces);
    }

    private function getTraits()
    {
        if (! count($this->traits))
            return '';

        $result = 'use ';

        foreach ($this->traits as $n => $trait) {
            if ($n == 0)
                $result .= $trait;
            else
                $result .= ",\n        " . $trait;
        }

        $result = rtrim($result, ',');

        return $result . ';';
    }

    private function getTranslatableTraits()
    {
        if (! count($this->translatableTraits))
            return '';

        $result = 'use ';

        foreach ($this->translatableTraits as $n => $trait) {
            if ($n == 0)
                $result .= $trait;
            else
                $result .= ",\n        " . $trait;
        }

        $result = rtrim($result, ',');

        return $result . ";\n\n    ";
    }

    private function use($class)
    {
        $tmp = explode('\\', $class);

        $this->use[] = $class;

        return end($tmp);
    }

    private function formatUse()
    {
        $result = '';

        foreach ($this->use as $class) {
            $result .= 'use ' . $class . ";\n";
        }

        return $result;
    }

    private function makeCacheable()
    {
        if (! $this->option('cacheable'))
            return;

        $this->interfaces[] = $this->use(Cacheable::class);
        $this->use(Cache::class);

        $filesystem = new Filesystem();
        $this->body .= "\n\n" . $filesystem->get(__DIR__ . '/stubs/blocks/cacheable.stub');
    }

    private function makePositionable()
    {
        if (! $this->option('positionable'))
            return;

        $this->fields[] = 'position';
        $this->interfaces[] = $this->use(Sortable::class);
        $this->traits[] = $this->use(Positionable::class);
    }

    private function makeImages()
    {
        if (! $this->option('images'))
            return;

        $this->traits[] = $this->use(WithImages::class);

        $this->body .= "\n    public \$imagesAutoName = true;";
    }

    private function makeTranslatable()
    {
        if (! $this->option('translatable'))
            return;

        $this->traits[] = $this->use(Translatable::class);

        $filesystem = new Filesystem();
        $this->body .= "\n\n" . $filesystem->get(__DIR__ . '/stubs/blocks/translatable.stub');

        (new Filesystem)->copy(
            $file = __DIR__ . '/stubs/EntityTranslation.stub',
            $file = $this->translationsPath() . '/' . $this->translationClass() . '.stub'
        );

        $body = '';
        $use = '';

        if ($this->option('sluggable')) {
            if ($this->isTranslatableField('slug') and $this->isTranslatableField('title')) {
                $this->translatableTraits[] = '\\' . Sluggable::class;
                $body .= "\n" . $filesystem->get(__DIR__ . '/stubs/blocks/sluggable.stub') . "\n";
            }
        }

        if ($this->option('cacheable')) {
            $this->translatableTraits[] = '\\' . ClearsRelatedModelCache::class;
        }

        $this->replace('{{ use }}', $use ? $use . "\n\n    " : '', $file);
        $this->replace('{{ body }}', $body, $file);
        $this->replace('{{ traits }}', $this->getTranslatableTraits(), $file);

        $this->replace('{{ class }}', $this->translationClass(), $file);
        $this->replace('{{ translatable_fields }}', $this->getTranslatableFields(), $file);
    }

    private function makeMigration()
    {
        if (! $this->option('migration'))
            return;

        $fileName = $this->migrationName();

        (new Filesystem)->copy(
            __DIR__ . '/stubs/migrations/entity.stub',
            $file = $this->migrationsPath() . '/' . $fileName . '.stub'
        );

        $this->replace('{{ translation_table_schema }}', (new Filesystem())->get(__DIR__ . '/stubs/migrations/entity_translatable.stub'), $file);

        $this->replace('{{ drop_translation }}', 'Schema::dropIfExists(\'{{ translation_table }}\');' . "\n        ", $file);

        $this->replace('{{ table }}', $this->tableName(), $file);
        $this->replace('{{ translation_table }}', $this->translationTable(), $file);
        $this->replace('{{ foreign_key }}', $this->modelName() . '_id', $file);
        $this->replace('{{ fields }}', $this->migrationFields(), $file);
        $this->replace('{{ translatable_fields }}', $this->migrationTranslatableFields(), $file);

        $this->replace('{{ class }}', $this->migrationClass(), $file);
    }

    private function fieldExists(string $value)
    {
        return in_array($value, $this->fields) or in_array($value, $this->translatableFields);
    }

    private function isTranslatableField(string $value)
    {
        return in_array($value, $this->translatableFields);
    }

    private function initFields()
    {
        $this->fields = $this->option('field') ?: [];
        $this->translatableFields = $this->option('tfield') ?: [];
    }

    private function makeUID()
    {
        if ($this->fieldExists('uid')) {
            $this->traits[] = $this->use(WithUID::class);
        }
    }

    private function makeStatus()
    {
        if ($this->fieldExists('status')) {
            $this->traits[] = $this->use(WithStatus::class);
            $this->casts['status'] = 'boolean';
        }
    }

    private function getCasts()
    {
        $casts = '';

        if (! count($this->casts))
            return '';

        foreach ($this->casts as $field => $type) {
            $casts .= "\n       '$field' => '$type',";
        }

        return "\n\n    protected \$casts = [" . $casts . "
    ];";
    }

    private function migrationsPath()
    {
        return database_path('migrations');
    }

    private function tableName()
    {
        return Str::plural($this->modelName());
    }

    private function translationTable()
    {
        return $this->modelName() . '_translation';
    }

    private function migrationName()
    {
        $class = $this->migrationClass();
        return date('Y_m_d') . '_' . (time() - strtotime("today")) . '_' . Str::snake($class);
    }

    private function migrationClass()
    {
        return 'Create' . Str::studly($this->tableName()) . 'Table';
    }

    private function migrationFields()
    {
        $result = '';

        foreach ($this->fields as $field) {
            $result .= $this->buildMigrationField($field) . "\n            ";
        }

        return $result;
    }

    private function migrationTranslatableFields()
    {
        $result = '';

        foreach ($this->translatableFields as $field) {
            $result .= $this->buildMigrationField($field) . "\n            ";
        }

        return $result;
    }

    protected function buildMigrationField($field)
    {
        $options = [];
        $type = 'string';

        if ($field == 'uid') {
            $options[] = 'nullable()';
            $options[] = 'unique()';
        }

        if ($field == 'price' or $field == 'old_price') {
            $options[] = 'nullable()';
            $type = 'integer';
        }

        if ($field == 'text' or $field == 'description' or $field == 'description_min') {
            $options[] = 'nullable()';
            $type = 'text';
        }

        if (strpos($field, '_id') !== false) {
            $options[] = 'nullable()';
            $type = 'unsignedInteger';
        }

        if ($field == 'status') {
            return '\InWeb\Base\Traits\WithStatus::statusColumn($table);';
        }

        if ($field == 'position') {
            return '\InWeb\Base\Traits\Positionable::positionColumn($table);';
        }

        return '$table->' . $type . '(\'' . $field . '\')' . (count($options) ? '->' . implode('->', $options) : '') . ';';
    }
}
