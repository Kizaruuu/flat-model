<?php

namespace Kizaru\FlatModel;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;

class BaseCommand extends Command
{
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
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function initEntity(string $table)
    {
        $data = DB::connection()->getPdo()->prepare("DESCRIBE `$table`");
        $data->execute();

        if ($data->rowCount() <= 0) {
            return;
        }

        while ($row = $data->fetch(PDO::FETCH_ASSOC)) {
            $this->schema[] = $row["Field"];
            $this->types[$row["Field"]] = $this->getType($row["Type"]);
            if ($row['Key'] === 'PRI') {
                $this->primaryKey = $row['Field'];
            }
        }
    }

    /**
     * @param string $type
     * @return string|null
     */
    protected function getType(string $type) {
        if (preg_match('/^(bigint|int|integer|smallint|tinyint|bit|numeric)(\(([\d]+)\))?/', $type, $matches)) {
            return 'int';
        }

        if (preg_match('/^(real|decimal|double|float)(\(([\d]+),([\d]+)\))?/', $type, $matches)) {
            return 'float';
        }

        if (preg_match('/^(longtext|text|mediumtext|tinytext|varchar|char|enum|set|varbinary|year|date|datetime|time|timestamp)(\(([\d]+)\))?/', $type, $matches)) {
            return 'string';
        }

        return null;
    }

    protected function getSchema(): array
    {
        return $this->schema;
    }

    protected function getTypes(): array
    {
        return $this->types;
    }

    /**
     * @return array
     */
    protected function getExcerptColumns(): array
    {
        return [];
    }

    /**
     * @return string
     */
    protected function getPrimaryKey(): string
    {
        if ($this->primaryKey) return $this->primaryKey;

        if (array_key_exists('id', $this->schema)) return 'id';

        return '';
    }

    /**
     * Get class name
     * @param $name
     * @return string
     */
    protected function getClassName($name): string
    {
        $className = array_filter(preg_split('/[^a-z]/i', $name));
        $className = array_map('ucfirst', $className);
        return join('', $className);
    }

    /**
     * @param string $name
     * @param string $baseNamespace
     * @return array
     */
    protected function getClassInfo(string $name, string $baseNamespace, string $baseFilePath): array
    {
        $arr = array_map('ucfirst', array_filter(explode('/', $name)));
        $className = array_pop($arr);
        $namespace = implode('\\', array_filter([$baseNamespace, ...$arr]));
        $filePath = implode('/', array_filter([$baseFilePath, ...$arr]));

        return [
            $className,
            $namespace,
            $filePath,
        ];
    }
}
