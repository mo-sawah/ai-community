<?php
/**
 * Singleton Trait
 * 
 * Provides singleton pattern implementation for classes
 */

if (!defined('ABSPATH')) {
    exit('Direct access forbidden.');
}

trait AI_Community_Singleton {
    
    /**
     * Singleton instances
     */
    private static array $instances = [];
    
    /**
     * Get singleton instance
     */
    public static function get_instance(...$args) {
        $class = static::class;
        
        if (!isset(self::$instances[$class])) {
            self::$instances[$class] = new static(...$args);
        }
        
        return self::$instances[$class];
    }
    
    /**
     * Prevent direct instantiation
     */
    protected function __construct() {
        // Override in child classes if needed
    }
    
    /**
     * Prevent cloning
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization
     */
    public function __wakeup() {
        throw new Exception('Cannot unserialize singleton');
    }
    
    /**
     * Reset singleton instance (mainly for testing)
     */
    public static function reset_instance(): void {
        $class = static::class;
        unset(self::$instances[$class]);
    }
}