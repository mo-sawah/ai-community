<?php
/**
 * AI Community Dependency Injection Container
 * 
 * Manages component dependencies and lifecycle
 */

if (!defined('ABSPATH')) {
    exit('Direct access forbidden.');
}

final class AI_Community_Container {
    
    use AI_Community_Singleton;
    
    /**
     * Registered services
     */
    private array $services = [];
    
    /**
     * Service instances
     */
    private array $instances = [];
    
    /**
     * Service definitions
     */
    private array $definitions = [];
    
    /**
     * Initialization status
     */
    private array $initialized = [];
    
    /**
     * Circular dependency detection
     */
    private array $resolving = [];
    
    /**
     * Register a service
     */
    public function register(string $name, string $class, array $dependencies = []): void {
        if (isset($this->services[$name])) {
            throw new InvalidArgumentException("Service '{$name}' is already registered");
        }
        
        if (!class_exists($class)) {
            throw new InvalidArgumentException("Class '{$class}' does not exist");
        }
        
        $this->services[$name] = $class;
        $this->definitions[$name] = [
            'class' => $class,
            'dependencies' => $dependencies,
            'singleton' => true, // Default to singleton
        ];
    }
    
    /**
     * Register a service with factory
     */
    public function register_factory(string $name, callable $factory, array $dependencies = []): void {
        if (isset($this->services[$name])) {
            throw new InvalidArgumentException("Service '{$name}' is already registered");
        }
        
        $this->services[$name] = $factory;
        $this->definitions[$name] = [
            'factory' => $factory,
            'dependencies' => $dependencies,
            'singleton' => true,
        ];
    }
    
    /**
     * Get a service instance
     */
    public function get(string $name) {
        if (!isset($this->services[$name])) {
            throw new InvalidArgumentException("Service '{$name}' is not registered");
        }
        
        // Return existing instance if singleton
        if (isset($this->instances[$name])) {
            return $this->instances[$name];
        }
        
        // Check for circular dependencies
        if (isset($this->resolving[$name])) {
            throw new RuntimeException("Circular dependency detected for service '{$name}'");
        }
        
        $this->resolving[$name] = true;
        
        try {
            $instance = $this->create_instance($name);
            
            if ($this->definitions[$name]['singleton']) {
                $this->instances[$name] = $instance;
            }
            
            unset($this->resolving[$name]);
            return $instance;
            
        } catch (Throwable $e) {
            unset($this->resolving[$name]);
            throw new RuntimeException("Failed to create service '{$name}': " . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Create service instance
     */
    private function create_instance(string $name) {
        $definition = $this->definitions[$name];
        
        // Handle factory-created services
        if (isset($definition['factory'])) {
            $dependencies = $this->resolve_dependencies($definition['dependencies']);
            return call_user_func_array($definition['factory'], $dependencies);
        }
        
        // Handle class-based services
        $class = $definition['class'];
        $dependencies = $this->resolve_dependencies($definition['dependencies']);
        
        // Use reflection to create instance with dependencies
        $reflection = new ReflectionClass($class);
        
        if (empty($dependencies)) {
            return $reflection->newInstance();
        }
        
        return $reflection->newInstanceArgs($dependencies);
    }
    
    /**
     * Resolve service dependencies
     */
    private function resolve_dependencies(array $dependencies): array {
        $resolved = [];
        
        foreach ($dependencies as $dependency) {
            if (is_string($dependency) && isset($this->services[$dependency])) {
                $resolved[] = $this->get($dependency);
            } else {
                $resolved[] = $dependency;
            }
        }
        
        return $resolved;
    }
    
    /**
     * Check if service is registered
     */
    public function has(string $name): bool {
        return isset($this->services[$name]);
    }
    
    /**
     * Initialize all registered services
     */
    public function init_all(): void {
        foreach (array_keys($this->services) as $name) {
            try {
                $instance = $this->get($name);
                
                // Call init method if exists
                if (method_exists($instance, 'init') && !isset($this->initialized[$name])) {
                    $instance->init();
                    $this->initialized[$name] = true;
                }
                
            } catch (Throwable $e) {
                error_log("Failed to initialize service '{$name}': " . $e->getMessage());
            }
        }
    }
    
    /**
     * Get all service instances
     */
    public function get_all(): array {
        return $this->instances;
    }
    
    /**
     * Reset container (mainly for testing)
     */
    public function reset(): void {
        $this->services = [];
        $this->instances = [];
        $this->definitions = [];
        $this->initialized = [];
        $this->resolving = [];
    }
    
    /**
     * Create service with auto-wiring
     */
    public function auto_wire(string $class) {
        if (!class_exists($class)) {
            throw new InvalidArgumentException("Class '{$class}' does not exist");
        }
        
        $reflection = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();
        
        if (!$constructor) {
            return $reflection->newInstance();
        }
        
        $parameters = $constructor->getParameters();
        $dependencies = [];
        
        foreach ($parameters as $parameter) {
            $type = $parameter->getType();
            
            if ($type && !$type->isBuiltin()) {
                $className = $type->getName();
                
                // Try to resolve from registered services first
                $serviceName = $this->find_service_by_class($className);
                if ($serviceName) {
                    $dependencies[] = $this->get($serviceName);
                    continue;
                }
                
                // Auto-wire the dependency
                if (class_exists($className)) {
                    $dependencies[] = $this->auto_wire($className);
                    continue;
                }
            }
            
            // Handle optional parameters
            if ($parameter->isOptional()) {
                $dependencies[] = $parameter->getDefaultValue();
            } else {
                throw new RuntimeException(
                    "Cannot resolve dependency '{$parameter->getName()}' for class '{$class}'"
                );
            }
        }
        
        return $reflection->newInstanceArgs($dependencies);
    }
    
    /**
     * Find service by class name
     */
    private function find_service_by_class(string $className): ?string {
        foreach ($this->definitions as $name => $definition) {
            if (isset($definition['class']) && $definition['class'] === $className) {
                return $name;
            }
        }
        
        return null;
    }
    
    /**
     * Register service with configuration
     */
    public function register_with_config(string $name, array $config): void {
        if (!isset($config['class']) && !isset($config['factory'])) {
            throw new InvalidArgumentException("Service config must have 'class' or 'factory'");
        }
        
        if (isset($config['class'])) {
            $this->register($name, $config['class'], $config['dependencies'] ?? []);
        } else {
            $this->register_factory($name, $config['factory'], $config['dependencies'] ?? []);
        }
        
        // Update definition with additional config
        $this->definitions[$name] = array_merge($this->definitions[$name], $config);
    }
    
    /**
     * Get service definition
     */
    public function get_definition(string $name): ?array {
        return $this->definitions[$name] ?? null;
    }
    
    /**
     * Remove service
     */
    public function remove(string $name): void {
        unset($this->services[$name], $this->instances[$name], $this->definitions[$name], $this->initialized[$name]);
    }
    
    /**
     * Tag services
     */
    private array $tags = [];
    
    /**
     * Tag a service
     */
    public function tag(string $name, string $tag): void {
        if (!isset($this->services[$name])) {
            throw new InvalidArgumentException("Service '{$name}' is not registered");
        }
        
        if (!isset($this->tags[$tag])) {
            $this->tags[$tag] = [];
        }
        
        $this->tags[$tag][] = $name;
    }
    
    /**
     * Get services by tag
     */
    public function get_tagged(string $tag): array {
        if (!isset($this->tags[$tag])) {
            return [];
        }
        
        $services = [];
        foreach ($this->tags[$tag] as $serviceName) {
            $services[$serviceName] = $this->get($serviceName);
        }
        
        return $services;
    }
    
    /**
     * Create lazy proxy
     */
    public function lazy(string $name): callable {
        return function() use ($name) {
            return $this->get($name);
        };
    }
    
    /**
     * Decorator pattern support
     */
    public function decorate(string $name, callable $decorator): void {
        if (!isset($this->services[$name])) {
            throw new InvalidArgumentException("Service '{$name}' is not registered");
        }
        
        $originalFactory = $this->definitions[$name]['factory'] ?? function() use ($name) {
            return $this->create_instance($name);
        };
        
        $this->definitions[$name]['factory'] = function() use ($decorator, $originalFactory) {
            $original = $originalFactory();
            return $decorator($original);
        };
    }
    
    /**
     * Service configuration
     */
    public function configure(string $name, array $config): void {
        if (!isset($this->services[$name])) {
            throw new InvalidArgumentException("Service '{$name}' is not registered");
        }
        
        $this->definitions[$name] = array_merge($this->definitions[$name], $config);
    }
    
    /**
     * Get container statistics
     */
    public function get_stats(): array {
        return [
            'registered_services' => count($this->services),
            'instantiated_services' => count($this->instances),
            'initialized_services' => count($this->initialized),
            'tags' => array_map('count', $this->tags),
        ];
    }
    
    /**
     * Debug information
     */
    public function debug(): array {
        $debug = [
            'services' => [],
            'stats' => $this->get_stats(),
        ];
        
        foreach ($this->services as $name => $class) {
            $debug['services'][$name] = [
                'class' => is_string($class) ? $class : 'Factory',
                'instantiated' => isset($this->instances[$name]),
                'initialized' => isset($this->initialized[$name]),
                'dependencies' => $this->definitions[$name]['dependencies'] ?? [],
            ];
        }
        
        return $debug;
    }
}