<?php

namespace InWeb\Base\Console\Commands;

use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use InWeb\Base\Console\RenamesStubs;
use InWeb\Base\Contracts\Cacheable;
use InWeb\Base\Entity;
use InWeb\Base\Traits\ClearsCache;
use InWeb\Base\Traits\ClearsRelatedModelCache;
use InWeb\Base\Traits\Positionable;
use InWeb\Base\Traits\Translatable;
use InWeb\Base\Traits\WithStatus;
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
                                        {--m|migration}
                                        {--a|admin}';

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
    protected $adminUse = [];
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
        if (!$this->hasValidNameArgument()) {
            return;
        }

        $file = $this->modelsPath() . '/' . $this->modelClass() . '.stub';

        (new Filesystem)->copy(
            $this->stubsPath('Entity.stub'),
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

        $createAdmin = $this->option('admin');

        if (!$createAdmin) {
            $createAdmin = $this->ask("Should also create Admin Resource class?");
        }

        if ($createAdmin)
            $this->makeAdmin();

        // Rename the stubs with the proper file extensions...
        $this->renameStubs();
    }

    public function makeAdmin()
    {
        $file = $this->adminPath() . '/Resources/' . $this->modelClass() . '.stub';

        (new Filesystem)->copy(
            $this->stubsPath('AdminResource.stub'),
            $file
        );

        $this->addStubToRename($file);

        $this->replace('{{ class }}', $this->modelClass(), $file);
        $this->replace('{{ model_class }}', '\\' . $this->modelNamespace(), $file);

        $label = $this->ask('Enter label for Admin Resource', Str::plural($this->modelClass()));
        $this->replace('{{ label }}', $label, $file);

        $singular_label = $this->ask('Enter singular label for Admin Resource', $this->modelClass());
        $this->replace('{{ singular_label }}', $singular_label, $file);

        $this->replace('{{ uri_key }}', $this->uriKey(), $file);

        $this->replace('{{ actions }}', $this->formatAdminActions(), $file);

        $translate = $this->confirm("Translate field titles to russian?", true);

        $this->getOutput()->section("Configure index fields");
        $this->replace('{{ index_fields }}', $this->formatAdminFields($this->getAdminIndexFields(), $translate), $file);

        $this->getOutput()->section("Configure detail fields");
        $this->replace('{{ detail_fields }}', $this->formatAdminFields($this->getAdminDetailFields(), $translate), $file);

        $this->replace('{{ use }}', $this->formatAdminUse(), $file);
    }

    public function getAllFields()
    {
        return [
            ...$this->fields,
            ...$this->translatableFields,
        ];
    }

    public function getAdminIndexFields()
    {
        $fields = $this->getAllFields();

        $order = [
            'index_image',
            'title',
            'title_single',
            'name',
            'last_name',
            'created_at',
            'updated_at',
            'page_id',
            'category_id',
            'link',
            'status',
        ];

        $result = [];
        $useFields = collect($fields)->flip()->only($order);

        if ($this->option('images'))
            $useFields->offsetSet('index_image', -1);

        foreach ($order as $field) {
            if (!isset($useFields[$field]))
                continue;

            $result[] = $field;
        }

        return $result;
    }

    public function getAdminDetailFields()
    {
        $fields = collect($this->getAllFields());

        $useFields = $fields->flip()->only($order = [
            'title',
            'slug',
            'title_single',
            'name',
            'last_name',
            'page_id',
            'category_id',
            'link',
            'description',
            'description_min',
            'text',
            'created_at',
            'updated_at',
            'status',
        ])->keys()->flip();

        $result = [];
        foreach ($order as $field) {
            if (!isset($useFields[$field]))
                continue;

            $result[] = $field;
        }

        foreach ($fields->flip()->except($order)->keys() as $field) {
            $result[] = $field;
        }

        return $result;
    }

    public function formatAdminActions()
    {
        $actions = [];

        if ($this->fieldExists('status')) {
            $actions[] = '(new ' . $this->adminUse('\InWeb\Admin\App\Actions\Publish') . '())';
            $actions[] = '(new ' . $this->adminUse('\InWeb\Admin\App\Actions\Hide') . '())';
        }

        if ($this->option('images')) {
            $actions[] = '(new ' . $this->adminUse('\Admin\ResourceTools\Images\Actions\RecreateThumbnails') . '())';
        }

        return implode(",
            ", $actions) . (count($actions) ? ',' : '');
    }

    public function formatAdminFields($availableFields, $translate = true)
    {
        $fields = [];

        $types = [
            0 => '\InWeb\Admin\App\Fields\Text',
            1 => '\InWeb\Admin\App\Fields\Textarea',
            2 => '\InWeb\Admin\App\Fields\Number',
            3 => '\InWeb\Admin\App\Fields\Boolean',
            4 => '\InWeb\Admin\App\Fields\Date',
            5 => '\InWeb\Admin\App\Fields\Datetime',
            6 => '\InWeb\Admin\App\Fields\Select',
            7 => '\InWeb\Admin\App\Fields\Editor',
            8 => '\InWeb\Admin\App\Fields\TreeField',
            9 => '\InWeb\Admin\App\Fields\Image',
        ];

        $titleMap = [
            'title'           => 'Заголовок',
            'title_single'    => 'В ед. числе',
            'name'            => 'Имя',
            'last_name'       => 'Фамилия',
            'slug'            => 'URL ID',
            'status'          => 'Опубликован',
            'description'     => 'Описание',
            'description_min' => 'Краткое описание',
            'text'            => 'Текст',
            'created_at'      => 'Создан',
            'updated_at'      => 'Изменён',
            'link'            => 'Ссылка',
            'page_id'         => 'Страница',
            'parent_id'       => 'Родитель',
            'category_Id'     => 'Категория',
        ];

        if (!$translate)
            $titleMap = [];

        $generatedData = [];

        foreach ($availableFields as $field) {
            $fieldTitle = $titleMap[$field] ?? Str::plural($field);

            $defaultType = $types[0];

            if ($field == 'position' and $this->option('positionable'))
                continue;

            if ($field == 'count' or $field == 'number' or $field == 'position' or str_ends_with('_number', $field) or str_ends_with('_count', $field))
                $defaultType = $types[2];
            if ($field == 'status' or str_starts_with('is_', $field))
                $defaultType = $types[3];
            if ($field == 'description' or $field == 'description_min')
                $defaultType = $types[1];
            if ($field == 'text')
                $defaultType = $types[7];
            if (str_ends_with('_at', $field) or str_ends_with('_date', $field) or str_starts_with('date_', $field))
                $defaultType = $types[5];
            if (str_ends_with('_id', $field))
                $defaultType = $types[6];

//            $this->line('Configure field: <fg=yellow;>' . $field . '</>');
            $type = $defaultType;
//            $type = $this->choice("Type: ", $types, $defaultType);

            $result = $this->adminUse($type) .
                "::make(__('$fieldTitle'), '$field')";

            if ($field == 'title') {
                $result .= "->link(\$this->editPath())";
            }

            if ($type == $types[3]) {
                $result .= "->fastEditBoolean()";
            }

            if ($field == 'index_image') {
                $this->adminUse('\InWeb\Admin\App\Fields\Image');
                $result = "Image::make('')->preview(function (\$value, \$model) {
                return optional(\$model->image)->getUrl();
            })";
            }

            $fields[$field] = $result;
            $generatedData[$field] = [
                'type'  => $type,
                'title' => $fieldTitle,
            ];
        }

        if ($this->option('images') and !in_array('index_image', $availableFields)) {
            $this->adminUse('\Admin\ResourceTools\Images\Images');
            $fields['?images'] = "(new Images())";
        }

        $showFieldList = function($fields) {
            $this->table(['Field', 'Code'], collect($fields)->map(function ($result, $field) {
                return [$field, $result];
            }));
        };

        while (true) {
            $showFieldList($fields);

            $field = $this->askWithCompletion("Enter field to configure: (leave empty to continue)", $fields, '');

            if (! $field)
                break;

            if (!isset($generatedData[$field]) or str_starts_with('?', $field)) {
                $this->warn('The is no field with name: ' . $field);
                continue;
            }

            $data = $generatedData[$field];

            $fieldTitle = $this->ask("Change Title: ", $data['title']);
            $type = $this->choice("Change Type: ", $types, $data['type']);

            $result = $this->adminUse($type) .
                "::make(__('$fieldTitle'), '$field')";

            if ($field == 'title') {
                $result .= "->link(\$this->editPath())";
            }

            if ($type == $types[3]) {
                $result .= "->fastEditBoolean()";
            }

            if ($field == 'status') {
                $result .= "->default(true)";
            }

            $fields[$field] = $result;
            $generatedData[$field] = [
                'type'  => $type,
                'title' => $fieldTitle,
            ];

            $this->getOutput()->success('Field updated');
        }

        return implode(",
            ", $fields) . (count($fields) ? ',' : '');
    }

    /**
     * Get the array of stubs that need PHP file extensions.
     *
     * @return array
     */
    protected function stubsToRename()
    {
        $files = [
            $this->modelsPath() . '/' . $this->modelClass() . '.stub',
            ...$this->stubsToRename,
        ];

        if ($this->option('translatable'))
            $files[] = $this->translationsPath() . '/' . $this->translationClass() . '.stub';

        if ($this->option('migration'))
            $files[] = $this->migrationsPath() . '/' . $this->migrationName() . '.stub';

        return $files;
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
    protected function adminPath()
    {
        return base_path('app/Admin');
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
        return Str::studly(str_replace('/', '\\', $this->relativeModelsPath())) . '\\' . $this->modelClass();
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

    /**
     * Get the tool's base name.
     *
     * @return string
     */
    protected function uriKey()
    {
        return Str::snake($this->argument('name'), '-');
    }

    private function hasValidNameArgument()
    {
        return $this->argument('name');
    }

    private function getInterfaces()
    {
        if (!count($this->interfaces))
            return '';

        return 'implements ' . implode(", ", $this->interfaces);
    }

    private function getTraits()
    {
        if (!count($this->traits))
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
        if (!count($this->translatableTraits))
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

        if (! in_array($class, $this->use))
            $this->use[] = $class;

        return end($tmp);
    }

    private function adminUse($class)
    {
        $tmp = explode('\\', $class);

        if (! in_array($class, $this->adminUse))
            $this->adminUse[] = $class;

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

    private function formatAdminUse()
    {
        $result = '';

        foreach ($this->adminUse as $class) {
            $result .= 'use ' . $class . ";\n";
        }

        return $result;
    }

    private function makeCacheable()
    {
        if (!$this->option('cacheable'))
            return;

        $this->interfaces[] = $this->use(Cacheable::class);
        $this->traits[] = $this->use(ClearsCache::class);

        $this->use(Cache::class);

        $filesystem = new Filesystem();
        $this->body .= "\n\n" . $filesystem->get($this->stubsPath('blocks/cacheable.stub'));
    }

    private function makePositionable()
    {
        if (!$this->option('positionable'))
            return;

        $this->fields[] = 'position';
        $this->interfaces[] = $this->use(Sortable::class);
        $this->traits[] = $this->use(Positionable::class);
    }

    private function makeImages()
    {
        if (!$this->option('images'))
            return;

        $this->traits[] = $this->use(WithImages::class);

        $this->body .= "\n    public \$imagesAutoName = true;";
    }

    private function makeTranslatable()
    {
        if (!$this->option('translatable'))
            return;

        $this->traits[] = $this->use(Translatable::class);

        $filesystem = new Filesystem();
        $this->body .= "\n\n" . $filesystem->get($this->stubsPath('blocks/translatable.stub'));

        (new Filesystem)->copy(
            $file = $this->stubsPath('EntityTranslation.stub'),
            $file = $this->translationsPath() . '/' . $this->translationClass() . '.stub'
        );

        $body = '';
        $use = '';

        if ($this->option('sluggable')) {
            if ($this->isTranslatableField('slug') and $this->isTranslatableField('title')) {
                $this->translatableTraits[] = '\\' . Sluggable::class;
                $body .= "\n" . $filesystem->get($this->stubsPath('blocks/sluggable.stub')) . "\n";
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
        if (!$this->option('migration'))
            return;

        $fileName = $this->migrationName();

        (new Filesystem)->copy(
            $this->stubsPath('migrations/entity.stub'),
            $file = $this->migrationsPath() . '/' . $fileName . '.stub'
        );

        $this->replace('{{ translation_table_schema }}', (new Filesystem())->get($this->stubsPath('migrations/entity_translatable.stub')), $file);

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

        foreach ($this->fields as $field) {
            if (strpos($field, 'is_') !== false) {
                $casts[$field] = 'boolean';
            }
        }
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

        if (!count($this->casts))
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
        return $this->modelName() . '_translations';
    }

    private function migrationName()
    {
        return Cache::driver('array')->rememberForever('imake:model:migration', function () {
            $class = $this->migrationClass();
            return date('Y_m_d') . '_' . (time() - strtotime("today")) . '_' . Str::snake($class);
        });
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

        if (strpos($field, 'is_') !== false) {
            $options[] = 'default(false)';
            $type = 'boolean';
        }

        if ($field == 'status') {
            return '\\' . $this->modelNamespace() . '::statusColumn($table);';
        }

        if ($field == 'position') {
            return '\\' . $this->modelNamespace() . '::positionColumn($table);';
        }

        return '$table->' . $type . '(\'' . $field . '\')' . (count($options) ? '->' . implode('->', $options) : '') . ';';
    }

    public function stubsPath($path = ''): string
    {
        return __DIR__ . '/../stubs/' . ltrim($path, '/');
    }
}
