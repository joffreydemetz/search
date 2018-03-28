<?php
/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace JDZ\Search\Seeker;

use JDZ\Database\Query\QueryInterface;
use JDZ\Database\Table\TableInterface;
use JDZ\Search\Criterias\Criterias;

/**
 * Search Seeker
 * 
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
class Seeker implements SeekerInterface
{
  /** 
   * Filter criterias object
   * 
   * @var   Criterias
   */
  protected $criterias;
  
  /** 
   * Table object
   * 
   * @var   TableInterface
   */
  protected $table;
  
  /** 
   * Database query object
   * 
   * @var   QueryInterface
   */
  protected $query;
  
  /** 
   * Where clause
   * 
   * @var   array
   */
  protected $where;
  
  /** 
   * Constructor
   * 
   * @param  TableInterface  $table      Table instance
   * @param  array           $criterias  Key/Value pairs of criterias   
   */
  public function __construct(TableInterface $table, array $criterias=[])
  {
    $this->table = $table;
    
    $db = Dbo();
    $this->query = $db->getQuery(true);
    
    $this->setCriterias($criterias);
  }
  
  /** 
   * Get the table instance
   */
  public function getTable()
  {
    return $this->table;
  }
  
  /** 
   * {@inheritDoc}
   */
  public function setCriterias(array $criterias=[])
  {
    $this->criterias = new Criterias($this->getCriteriasDefaults(), $this->getCriteriasValues($criterias));
  }
  
  /** 
   * {@inheritDoc}
   */
  public function count()
  {
    $db = Dbo();
    $this->query->clear();
    $this->setCountQuery();
    $db->setQuery($this->query);
    
    return (int)$db->loadResult();
  }
  
  /** 
   * {@inheritDoc}
   */
  public function list()
  {
    $db = Dbo();
    
    $this->query->clear();
    $this->setListQuery();
    
    if ( $limit = $this->criterias->get('limit') ){
      $db->setQuery($this->query, $this->criterias->get('start'), $limit);
    }
    else {
      $db->setQuery($this->query);
    }
    
    $rows = $db->loadObjectList();
    return $this->formatResults($rows);
  }
  
  /** 
   * {@inheritDoc}
   */
  public function getCriteria($key, $default=null)
  {
    return $this->criterias->get($key, $default);
  }
  
  /** 
   * Get the list of default criteria values
   * 
   * @param   array   $criterias  Key/Value pairs
   * @return   array   The criterias list
   */
  protected function getCriteriasDefaults(array $criterias=[])
  {
    $criterias['start']       = 0;
    $criterias['limit']       = 0;
    $criterias['ordering']    = $this->table->getDefaultOrdering();
    $criterias['orderingDir'] = 'ASC';
    $criterias['stype']       = 'contains';
    
    if ( $this->table->publishingAble() ){
      $criterias['published'] = 1;
    }
    
    if ( $this->table->hasField('title') ){
      $criterias['title'] = '';
    }
    
    return $criterias;
  }
  
  /** 
   * Get the list of criteria setted values
   * 
   * @param  array  $criterias  Key/Value pairs
   * @return array  The criterias list
   */
  protected function getCriteriasValues(array $criterias=[])
  {
    return $criterias;
  }
  
  /**
   * Format results
   * 
   * @param  array  $results
   * @return array  Formatted results
   */
  protected function formatResults(array $results)
  {
    return $results;
  }
  
  /** 
   * Add search filters to the count query
   * 
   * @return   void
   */
  protected function setCountQuery()
  {
    $db = Dbo();
    
    $this->query->select('COUNT(DISTINCT('.$db->qn('a.'.$this->table->getTblKey()).'))');
    $this->query->from($this->table->getTbl().' AS a');
    
    if ( $this->table->publishingAble() ){
      if ( $sql = $this->addBooleanField('published') ){
        $this->query->where($sql);
      }
    }
    
    if ( $or = $this->setQueryWhere(true) ){
      $this->query->where('('.implode(' OR ', $or).')');
    }
  }
  
  /** 
   * Add search filters to the list query
   * 
   * @return   void
   */
  protected function setListQuery()
  {
    $db = Dbo();
    
    $this->query->select('a.*');
    $this->query->from($this->table->getTbl().' AS a');
    $this->query->order($db->qn($this->criterias->get('ordering')).' '.$this->criterias->get('orderingDir'));
    $this->query->group('a.'.$this->table->getTblKey());
    
    if ( $this->table->publishingAble() ){
      if ( $sql = $this->addBooleanField('published') ){
        $this->query->where($sql);
      }
    }
    
    if ( $or = $this->setQueryWhere(false) ){
      $this->query->where('('.implode(' OR ', $or).')');
    }
    
    // echo str_replace('#__', 'kdg_', (string)$this->query);
    // exit(0);
  }
  
  /** 
   * Add search filters to the list query
   * 
   * @param    bool  $noSelect   True for a COUNT() query
   * @return   array|null
   */
  protected function setQueryWhere($noSelect=false)
  {
    $or = null;
    
    if ( $sql = $this->addTextField('title', 'a.', false) ){
      $or[] = $sql;
    }
    
    return $or;
  }
  
  /** 
   * Add a boolean filter field
   * 
   * @param   string    $field    The field name
   * @param   string    $prefix   The field table prefix
   * @param   bool      $force    True to force event empty filter value
   * @return   string    The sql statement
   */
  protected function addBooleanField($field, $prefix='a.', $force=true)
  {
    if ( !$this->table->hasField($field) ){
      return false;
    }
    
    if ( !$this->criterias->has($field) ){
      return false;
    }
    
    $db = Dbo();
    $searchValue = (bool)$this->criterias->get($field);
    
    if ( $force === false && $searchValue === false ){
      return false;
    }
    
    return $db->qn($prefix.$field).' = '.($searchValue?'1':'0');
  }
  
  /** 
   * Add a integer filter field
   * 
   * @param   string    $field    The field name
   * @param   string    $prefix   The field table prefix
   * @param   bool      $force    True to force event empty filter value
   * @return   string    The sql statement
   */
  protected function addIntField($field, $prefix='a.', $force=true)
  {
    if ( !$this->table->hasField($field) ){
      return false;
    }
    
    if ( !$this->criterias->has($field) ){
      return false;
    }
    
    $db = Dbo();
    $searchValue = (int)$this->criterias->get($field);
    
    if ( $force === false && $searchValue === 0 ){
      return false;
    }
    
    return $db->qn($prefix.$field).' = '.$searchValue;
  }
  
  /** 
   * Add a text filter field
   * 
   * @param   string    $field    The field name
   * @param   string    $prefix   The field table prefix
   * @param   bool      $force    True to force event empty filter value
   * @return   string    The sql statement
   */
  protected function addTextField($field, $prefix='a.', $force=true)
  {
    if ( !$this->table->hasField($field) ){
      return false;
    }
    
    if ( !$this->criterias->has($field) ){
      return false;
    }
    
    $searchValue = (string)$this->criterias->get($field);
    
    if ( $force === false && $searchValue === '' ){
      return false;
    }
    
    $db = Dbo();
    $stype  = $this->criterias->get('stype', 'contains');
    $searchValue = str_replace("'", "\'", $searchValue);
    
    if ( $stype === 'strict' ){
      return $db->qn($prefix.$field).' LIKE '.$db->Quote($searchValue);
    }
    
    if ( $stype === 'startswith' ){
      return $db->qn($prefix.$field).' LIKE'.$db->Quote($searchValue.'%');
    }
    
    if ( $stype === 'endswith' ){
      return $db->qn($prefix.$field).' LIKE '.$db->Quote('%'.$searchValue);
    }
    
    return $db->qn($prefix.$field).' LIKE '.$db->Quote('%'.str_replace(' ', '%', $searchValue).'%');
  }
}
