<?php

namespace Laf\Util;

use Laf\Exception\MissingConfigParamException;

/**
 * Process-wide configuration: a flat key => value map (keys like 'database.hostname').
 *
 * get() throws MissingConfigParamException for an unknown key; has(), getBool(), getString() and
 * getInt() are the forgiving readers for optional keys. load() / loadFromFile() take a nested
 * array (['database' => ['hostname' => 'x']] becomes 'database.hostname'); a list stays one
 * value. saveToFile() writes the current map as a PHP or JSON file loadFromFile() reads back.
 */
class Settings
{
    private static ?Settings $instance = null;

    /** @var array<string, mixed> */
    private array $properties = [];

    private function __construct()
    {
    }

    public static function getInstance(): Settings
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function setProperty(string $key, mixed $value): void
    {
        $this->properties[$key] = $value;
    }

    /**
     * @throws MissingConfigParamException for an unknown key
     */
    public function getProperty(string $key): mixed
    {
        if ($this->propertyExists($key)) {
            return $this->properties[$key];
        }
        throw new MissingConfigParamException(sprintf("Error: Setting %s doesnt exist", $key));
    }

    /** Whether the key is configured (a null value counts as configured) */
    public function propertyExists(string $key): bool
    {
        return array_key_exists($key, $this->properties);
    }

    public function removeProperty(string $key): void
    {
        unset($this->properties[$key]);
    }

    /**
     * Every setting, keyed by its dotted name.
     * @return array<string, mixed>
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    /**
     * Load a (nested) array: string-keyed arrays are flattened with dots, lists are kept as values.
     * @param array<string, mixed> $values
     */
    public function loadFromArray(array $values, string $prefix = ''): void
    {
        foreach ($values as $key => $value) {
            $name = $prefix === '' ? (string)$key : $prefix . '.' . $key;
            if (is_array($value) && $value !== [] && !self::isList($value)) {
                $this->loadFromArray($value, $name);
            } else {
                $this->properties[$name] = $value;
            }
        }
    }

    /**
     * Load settings from a file: a .php file returning an array, a .json file holding an object,
     * or a .ini file (sections become prefixes). Returns false when the file is missing, unreadable
     * or does not hold an array; nothing is loaded then.
     */
    public function loadFromFile(?string $file): bool
    {
        if ($file === null || $file === '' || !is_file($file) || !is_readable($file)) {
            return false;
        }
        $values = self::readFile($file);
        if (!is_array($values)) {
            return false;
        }
        $this->loadFromArray($values);
        return true;
    }

    /**
     * Save every setting to a .php file (returning the array) or a .json file. Returns false
     * without a file name, for another extension, or when the file cannot be written.
     */
    public function saveToFile(?string $file = null): bool
    {
        if ($file === null || $file === '') {
            return false;
        }
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if ($extension === 'php') {
            $content = "<?php\n\nreturn " . var_export($this->properties, true) . ";\n";
        } elseif ($extension === 'json') {
            $encoded = json_encode($this->properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($encoded === false) {
                return false;
            }
            $content = $encoded . "\n";
        } else {
            return false;
        }
        return @file_put_contents($file, $content) !== false;
    }

    /**
     * @throws MissingConfigParamException for an unknown key
     */
    public static function get(string $key): mixed
    {
        return self::getInstance()->getProperty($key);
    }

    /**
     * Whether a key is configured (a null value counts as configured)
     */
    public static function has(string $key): bool
    {
        return self::getInstance()->propertyExists($key);
    }

    /**
     * A boolean setting: true/false, 1/0, "1"/"0", "yes"/"no", "on"/"off" are accepted;
     * $default when the key is missing or the value is not a recognised boolean.
     */
    public static function getBool(string $key, bool $default = false): bool
    {
        if (!self::has($key)) {
            return $default;
        }
        $value = self::getInstance()->getProperty($key);
        if (is_bool($value)) {
            return $value;
        }
        if ($value === null || is_array($value) || is_object($value)) {
            return $default;
        }
        $parsed = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        return $parsed ?? $default;
    }

    /**
     * A string setting; $default when the key is missing or the value is not a scalar.
     */
    public static function getString(string $key, string $default = ''): string
    {
        if (!self::has($key)) {
            return $default;
        }
        $value = self::getInstance()->getProperty($key);
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        return is_scalar($value) ? (string)$value : $default;
    }

    /**
     * An integer setting; $default when the key is missing or the value is not a whole number.
     */
    public static function getInt(string $key, int $default = 0): int
    {
        if (!self::has($key)) {
            return $default;
        }
        $value = self::getInstance()->getProperty($key);
        if (is_int($value)) {
            return $value;
        }
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        if ((is_string($value) || is_float($value)) && preg_match('/^\s*[+-]?\d+\s*$/', (string)$value)) {
            return (int)$value;
        }
        return $default;
    }

    public static function set(string $key, mixed $value): void
    {
        self::getInstance()->setProperty($key, $value);
    }

    public static function remove(string $key): void
    {
        self::getInstance()->removeProperty($key);
    }

    /**
     * Load a (nested) array of settings, see loadFromArray().
     * @param array<string, mixed> $values
     */
    public static function load(array $values, string $prefix = ''): void
    {
        self::getInstance()->loadFromArray($values, $prefix);
    }

    /**
     * Settings from environment variables: every variable starting with $prefix becomes a
     * dotted key, lower-cased, with "__" as the dot: LAF_DATABASE__HOSTNAME -> database.hostname,
     * LAF_SETTINGS__USE_PRETTY_URL -> settings.use_pretty_url. Values stay strings ("true", "0"):
     * read flags with getBool() / getInt(). $env defaults to $_ENV merged with getenv().
     * @param array<string, mixed>|null $env
     * @return int variables loaded
     */
    public static function loadFromEnvironment(string $prefix = 'LAF_', ?array $env = null): int
    {
        if ($env === null) {
            $env = array_merge(getenv() ?: [], $_ENV);
        }
        $loaded = 0;
        foreach ($env as $name => $value) {
            $name = (string)$name;
            if ($prefix !== '' && strncmp($name, $prefix, strlen($prefix)) !== 0) {
                continue;
            }
            $key = strtolower(str_replace('__', '.', substr($name, strlen($prefix))));
            if ($key === '' || !is_scalar($value)) {
                continue;
            }
            self::set($key, (string)$value);
            $loaded++;
        }
        return $loaded;
    }

    /**
     * Settings from a .env style file (KEY=value lines, # comments, optional single or double
     * quotes around the value, \n inside double quotes), mapped like loadFromEnvironment().
     * Variables already present in the real environment win over the file.
     * @return int|false variables loaded, false when the file cannot be read
     */
    public static function loadEnvFile(string $file, string $prefix = 'LAF_'): int|false
    {
        $content = @file_get_contents($file);
        if ($content === false) {
            return false;
        }
        $values = [];
        foreach (preg_split('/\r\n|\r|\n/', $content) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            if (strncmp($name, 'export ', 7) === 0) {
                $name = trim(substr($name, 7));
            }
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
                continue;
            }
            $value = trim($value);
            if ($value !== '' && ($value[0] === '"' || $value[0] === "'") && strlen($value) >= 2 && substr($value, -1) === $value[0]) {
                $quote = $value[0];
                $value = substr($value, 1, -1);
                if ($quote === '"') {
                    $value = str_replace(['\\n', '\\"'], ["\n", '"'], $value);
                }
            } else {
                $value = trim((string)preg_replace('/\s+#.*$/', '', $value));
            }
            $real = getenv($name);
            $values[$name] = $real !== false ? $real : ($_ENV[$name] ?? $value);
        }
        return self::loadFromEnvironment($prefix, $values);
    }

    /** @return mixed the file's content: an array for a valid settings file */
    private static function readFile(string $file): mixed
    {
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if ($extension === 'php') {
            return include $file;
        }
        $content = file_get_contents($file);
        if ($content === false) {
            return null;
        }
        if ($extension === 'json') {
            return json_decode($content, true);
        }
        if ($extension === 'ini') {
            $parsed = parse_ini_string($content, true, INI_SCANNER_TYPED);
            return $parsed === false ? null : $parsed;
        }
        return null;
    }

    /** @param array<mixed> $value */
    private static function isList(array $value): bool
    {
        return array_keys($value) === range(0, count($value) - 1);
    }
}
