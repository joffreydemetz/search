<?php
/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace JDZ\Search\Saver;

use JDZ\Database\Table\TableInterface;

/**
 * Abstract Seeker Saver
 * 
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
class Saver implements SaverInterface
{
  /** 
   * Table object
   * 
   * @var   TableInterface
   */
  protected $table;
  
  /** 
   * Data to save
   * 
   * @var   array
   */
  protected $data;
  
  /** 
   * Saver instances
   * 
   * @var   [Saver]
   */
  protected static $instances;
  
  /** 
   * Get a seeker instance
   * 
   * @param  string  $seeker Seeker name
   * @param  array   $data   Key/Value pairs of data to save
   * @return   Saver   The saver instance
   */
  public static function getInstance($seeker, array $data=[])
  {
    if ( !isset(self::$instances) ){
      self::$instances = [];
    }
    
    if ( !isset(self::$instances[$seeker]) ){
      $table = Table($seeker);
      self::$instances[$seeker] = new self($table, $data);
    }
    
    return self::$instances[$seeker];
  }
  
  /** 
   * Constructor
   * 
   * @param  TableInterface  $table  Table instance
   * @param  array           $data   Key/Value pairs of data to save   
   */
  public function __construct(TableInterface $table, array $data=[])
  {
    $this->table = $table;
    
    $this->data = array_merge([
      'id_searchtype' => 0,
      'source'        => '',
      'term'          => '',
      'filters'       => null,
      'nbResults'     => 0,
    ], $data);
    
    if ( !$this->data['filters'] ){
      $this->data['filters'] = [];
    }
    
    $this->data['filters'] = (object)$this->data['filters'];
  }
  
  /** 
   * {@inheritDoc}
   */
  public function save()
  {
    $data = $this->data;
    $data['filters'] = json_encode($data['filters']);
    
    $table = Table('Search');
    if ( $table->save($data) ){
      return true;
    }
    return false;
  }
  
  /** 
   * {@inheritDoc}
   */
  public function set($key, $value)
  {
    $this->data[$key] = $value;
    return $this;
  }
  
  /** 
   * {@inheritDoc}
   */
  public function setFilter($key, $value)
  {
    $this->data['filters']{$key} = $value;
    return $this;
  }
}
