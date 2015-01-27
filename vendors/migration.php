<?php

/**
 * Base migration class
 *
 * @link          http://github.com/jrbasso/migrations
 * @package       migrations
 * @subpackage    migrations.vendors
 * @since         v 0.1
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

/**
 * Migration
 */
class Migration {

    /**
     * Uses models
     *
     * @var array
     * @access public
     */
    public $uses = array();

    /**
     * Stop up/down on error
     *
     * @var boolean
     * @access public
     */
    public $stopOnError = true;

    /**
     * DataSource link
     *
     * @var object
     * @access protected
     */
    public $_db = null;

    /**
     * Schell that called this class
     *
     * @var object
     * @access protected
     */
    public $_shell;

    /**
     * Fake CakeSchema
     *
     * @var object
     * @access private
     */
    public $__fakeSchema = null;

    /**
     * Error
     *
     * @var boolean
     * @access private
     */
    public $__error = false;

    /**
     * Constructor
     */
    public function __construct($shell = null) {
        $this->_shell = $shell;
        $this->_db = $shell->db;
        $this->__fakeSchema = new CakeSchema();

        $uses = $this->_getUses();

        foreach ($uses as $use) {
            $varName = Inflector::camelize($use);

            $this->{$varName} = ClassRegistry::init(array('class' => $use, 'alias' => $use, 'ds' => $shell->connection));

            if (!$this->{$varName}) {
                $this->_shell->err(String::insert(
                                __d('migrations', 'Model ":model" not exists.', true), array('model' => $use)
                ));
                $this->_shell->_stop();
            }
        }
    }

    /**
     * Get uses from parent classes
     *
     * @return array
     * @access protected
     */
    public function _getUses() {
        // Uses
        $uses = get_class_vars('AppMigration');
        $uses = $uses['uses'];
        if (!is_array($uses)) {
            $uses = (array) $uses;
        }
        if (!is_array($this->uses)) {
            $this->uses = (array) $this->uses;
        }
        return array_unique(array_merge($uses, $this->uses));
    }

    /**
     * Get a model from a table name
     *
     * @param string $tableName
     * @return object Model
     * @access public
     */
    public function getModel($tableName) {
        if (!in_array($tableName, $this->_db->listSources())) {
            return null;
        }
        return new AppModel(array('name' => Inflector::camelize(Inflector::singularize($tableName)), 'table' => $tableName, 'ds' => $this->_db->configKeyName));
    }
    
    /**
     * Run a SQL command
     * @param type $sql
     * @return boolean
     */
    public function run($sql){
        if ($this->stopOnError && $this->__error) {
            return false;
        }
        
        if ($this->_db->execute($sql)) {
            $this->out('ok');
            return true;
        }
        $this->out('nok');
        $this->__error = true;
        return false;
        
    }

    /**
     * Output a message to console
     *
     * @param string $message
     * @param boolean $newLine
     * @return void
     * @access public
     */
    public function out($message, $newLine = true) {
        if ($this->_shell) {
            $this->_shell->out($message, $newLine);
        }
    }

    /**
     * Install revision
     *
     * @return boolean
     * @access public
     */
    public function install() {
        return $this->_exec('up', 'Install');
    }

    /**
     * Uninstall revision
     *
     * @return boolean
     * @access public
     */
    public function uninstall() {
        return $this->_exec('down', 'Uninstall');
    }

    /**
     * Execute Install and Uninstall methods
     *
     * @param string $command Can be 'up' or 'down'
     * @param string $callback Name of callback function
     * @return boolean
     * @access protected
     */
    public function _exec($command, $callback) {
        $this->__error = false;
        if (!method_exists($this, $command)) {
            $this->out(String::insert(__d('migrations', '> Method ":method" not implemented. Skipping...', true), array('method' => $command)));
            return true;
        }
        $method = 'before' . $callback;
        if (method_exists($this, $method)) {
            if (!$this->$method()) {
                return false;
            }
        }
        $ok = $this->_db->begin($this->__fakeSchema);
        $this->$command();
        if ($this->stopOnError) {
            if ($this->__error) {
                $ok = false;
            }
        }
        if ($ok) {
            $this->_db->commit($this->__fakeSchema);
        } else {
            $this->_db->rollback($this->__fakeSchema);
        }
        $method = 'after' . $callback;
        if (method_exists($this, $method)) {
            $this->$method($ok);
        }
        return $ok;
    }

}

if (!class_exists('CakeSchema')) {

    class CakeSchema {
        
    }

}