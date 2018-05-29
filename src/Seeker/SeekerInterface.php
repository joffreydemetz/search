<?php
/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace JDZ\Search\Seeker;

use JDZ\Database\DatabaseInterface;
use JDZ\Database\Table\TableInterface;

/**
 * Seeker interface
 * 
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
interface SeekerInterface
{
  /** 
   * Set DBO
   * 
   * @param  DatabaseInterface  $dbo
   * @return $this
   */
  public function setDbo(DatabaseInterface $dbo);
  
  /** 
   * Set table
   * 
   * @param  TableInterface  $table
   * @return $this
   */
  public function setTable(TableInterface $table);
  
  /** 
   * Count elements
   * 
   * @param  array  $criterias  Key/Value pairs of criterias   
   * @return $this
   */
  public function setCriterias(array $criterias);
  
  /** 
   * Get table
   * 
   * @return  TableInterface 
   */
  public function getTable();
  
  /** 
   * Get a criteria value
   * 
   * @param   string  Criteria key
   * @param   mixed   Default value if not set
   * @return  array   The records filtered by criterias
   */
  public function getCriteria($key, $default=null);

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
}
