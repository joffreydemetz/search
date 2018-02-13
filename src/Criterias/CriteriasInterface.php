<?php
/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace JDZ\Search\Criterias;

/**
 * Criterias interface
 * 
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
interface CriteriasInterface
{
  /** 
   * Set the criterias
   * 
   * @param  array   $criterias  Key/Value pairs of criterias
   * @return  void
   */
  public function setProperties(array $criterias=[]);
  
  /** 
   * Set the criterias default values
   * 
   * @param  array   $criterias  Key/Value pairs of criterias
   * @return  void
   */
  public function defProperties(array $criterias=[]);
  
  /** 
   * Set a criteria default value
   * 
   * @param  string   $key   The criteria name
   * @param  mixed    $value The criteria default value
   * @return  void
   */
  public function def($key, $value);
  
  /** 
   * Set a criteria value
   * 
   * @param  string   $key   The criteria name
   * @param  mixed    $value The criteria value
   * @return  void
   */
  public function set($key, $value);
  
  /** 
   * Set a criteria default value
   * 
   * @param  string   $key     The criteria name
   * @param  mixed    $default The criteria default value if not set
   * @return  void
   */
  public function get($key, $default=null);
  
  /** 
   * Does a criteria exist
   * 
   * @param  string   $key     The criteria name
   * @return  void
   */
  public function has($key);
}
