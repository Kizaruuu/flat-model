<?php

namespace Kizaru\FlatModel;

use Exception;

class MakeFlatResource extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:c-resource {name} {--table=}';

    /**
     * @var array
     */
    protected $types = [];

    /**
     * @var array
     */
    protected $schema = [];

    protected $primaryKey = '';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $resource = trim($this->argument('name'), '"\'');
        $entity = $this->option('table');

        try {
            if (empty($resource)) {
                throw new \Exception('Resource name cannot be empty!');
            }

            if (! defined('APP_PATH')) {
                define('APP_PATH', app_path());
            }

            $this->info('Creating resource ...');

            $this->initEntity($entity);
            $res = $this->createResource($resource, $entity);

            $this->info($res);

        } catch (Exception $exception) {
            $this->error($exception->getMessage());
        }
    }

    /**
     * Create API resource
     * @param string $resource
     * @param string|null $entity
     * @return string
     * @throws Exception
     */
    public function createResource(string $resource, string $entity = null)
    {
        $resource = array_map('ucfirst', array_filter(preg_split('/[^a-z0-9]/i', $resource)));
        $resourceName = $this->getClassName(array_pop($resource)) . 'Resource';

        $resourcePath = APP_PATH . '/Http/Resources';
        $namespace = 'App\\Http\\Resources';

        if (! empty($resource)) {
            $resourcePath .= '/' . join('/', $resource);
            $namespace .= '\\' . join('\\', $resource);
        }

        if (! file_exists($resourcePath)) {
            mkdir($resourcePath, 0775, true);
        }

        $dataMapContent = '';

        if (! empty($entity)) {
            $schema = $this->getSchema();

            foreach ($schema as $field) {
                $dataMapContent .= str_repeat(' ', 12) . "'$field' => \$this->$field,\n";
            }

            $dataMapContent = trim($dataMapContent, "\n");
        }

        $resourceFile = $resourcePath .'/'. $resourceName . '.php';
        if (! file_exists($resourceFile)) {
            $content = <<<CONTENT
<?php

namespace $namespace;

use Illuminate\Http\Resources\Json\JsonResource;

class $resourceName extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request \$request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray(\$request)
    {
        return [
            # [auto-gen-resource]
$dataMapContent
            # [/auto-gen-resource]
        ];
    }
}
CONTENT;

            // Create resource file
            $file = fopen($resourceFile, 'w');
            fwrite($file, $content);
            fclose($file);

            $ret = 'Resource "\\' . $namespace . '\\' . $resourceName . '" was created!';
        } else {
            throw new Exception("$resourceFile is existed");
        }

        return $ret;
    }
}
