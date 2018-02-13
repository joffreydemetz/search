<?php
/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace JDZ\Search\Criterias;

/**
 * Search Criterias
 * 
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
class Criterias implements CriteriasInterface
{
  /** 
   * Key/Value pairs of criterias
   * 
   * @var   array
   */
  protected $data;
  
  /** 
   * Constructor
   * 
   * @param  array   $defaults  Key/Value pairs of default criterias
   */
  public function __construct(array $defaults=[], array $values=[])
  {
    if ( $defaults ){
      $this->defProperties($defaults);
    }
    
    if ( $values ){
      $this->setProperties($values);
    }
  }
  
  /** 
   * {@inheritDoc}
   */
  public function setProperties(array $criterias=[])
  {
    foreach($criterias as $key => $value){
      $this->set($key, $value);
    }
  }
  
  /** 
   * {@inheritDoc}
   */
  public function defProperties(array $criterias=[])
  {
    foreach($criterias as $key => $value){
      $this->def($key, $value);
    }
  }
  
  /** 
   * {@inheritDoc}
   */
  public function def($key, $value)
  {
    if ( !isset($this->data[$key]) ){
      $this->data[$key] = $value;
    }
  }
  
  /** 
   * {@inheritDoc}
   */
  public function set($key, $value)
  {
    $this->data[$key] = $value;
  }
  
  /** 
   * {@inheritDoc}
   */
  public function get($key, $default=null)
  {
    if ( isset($this->data[$key]) ){
      return $this->data[$key];
    }
    
    return $default;
  }
  
  /** 
   * {@inheritDoc}
   */
  public function has($key)
  {
    return ( isset($this->data[$key]) );
  }
}
