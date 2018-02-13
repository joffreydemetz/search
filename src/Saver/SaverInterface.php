<?php
/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace JDZ\Search\Criterias;

/**
 * Saver interface
 * 
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
interface SaverInterface
{
  /** 
   * Save the new record
   * 
   * @return   bool  True if saved successfully
   */
  public function save();
  
  /** 
   * Set a property value
   * 
   * @param   string  $key     The property key
   * @param   mixed   $value   The property value
   * @return   void
   */
  public function set($key, $value);
  
  /** 
   * Set a filter value
   * 
   * @param   string  $key     The filter key
   * @param   mixed   $value   The filter value
   * @return   void
   */
  public function setFilter($key, $value);
}
