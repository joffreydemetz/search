<?php
/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace JDZ\Search\Seeker;

/**
 * Seeker interface
 * 
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
interface SeekerInterface
{
  /** 
   * Count elements
   * 
   * @param  array  $criterias  Key/Value pairs of criterias   
   * @return void
   */
  public function setCriterias(array $criterias=[]);

  /** 
   * Count elements
   * 
   * @return   int   The number of records filtered by criterias
   */
  public function count();
  
  /** 
   * List elements
   * 
   * @return   array   The records filtered by criterias
   */
  public function list();
  
  /** 
   * Get a criteria value
   * 
   * @param   string  Criteria key
   * @param   mixed   Default value if not set
   * @return  array   The records filtered by criterias
   */
  public function getCriteria($key, $default=null);
}
