<?php
/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace JDZ\Search\Seeker;

use JDZ\Database\DatabaseInterface;
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
   * Database object
   * 
   * @var   DatabaseInterface
   */
  protected $dbo;
  
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
   * {@inheritDoc}
   */
  public function setDbo(DatabaseInterface $dbo)
  {
    $this->dbo   = $dbo;
    $this->query = $this->dbo->getQuery(true);
    return $this;
  }
  
  /** 
   * {@inheritDoc}
   */
  public function setTable(TableInterface $table)
  {
    $this->table = $table;
    return $this;
  }
  
  /** 
   * {@inheritDoc}
   */
  public function setCriterias(array $criterias)
  {
    $this->criterias = new Criterias($this->getCriteriasDefaults(), $this->getCriteriasValues($criterias));
    return $this;
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
  public function getCriteria($key, $default=null)
  {
    return $this->criterias->get($key, $default);
  }
  
  /** 
   * {@inheritDoc}
   */
  public function count()
  {
    $this->query->clear();
    $this->setCountQuery();
    $this->dbo->setQuery($this->query);
    
    return (int)$this->dbo->loadResult();
  }
  
  /** 
   * {@inheritDoc}
   */
  public function list()
  {
    $this->query->clear();
    $this->setListQuery();
    
    if ( $limit = $this->criterias->get('limit') ){
      $this->dbo->setQuery($this->query, $this->criterias->get('start'), $limit);
    }
    else {
      $this->dbo->setQuery($this->query);
    }
    
    $rows = $this->dbo->loadObjectList();
    return $this->formatResults($rows);
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
    $criterias['ordering']    = 'a.id';
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
    $this->query->select('COUNT(a.'.$this->table->getTblKey().')');
    // $this->query->select('COUNT(DISTINCT('.$this->dbo->qn('a.'.$this->table->getTblKey()).'))');
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
    $this->query->select('a.*');
    $this->query->from($this->table->getTbl().' AS a');
    $this->query->order($this->dbo->qn($this->criterias->get('ordering')).' '.$this->criterias->get('orderingDir'));
    $this->query->group('a.'.$this->table->getTblKey());
    
    if ( $this->table->publishingAble() ){
      if ( $sql = $this->addBooleanField('published') ){
        $this->query->where($sql);
      }
    }
    
    if ( $or = $this->setQueryWhere(false) ){
      $this->query->where('('.implode(' OR ', $or).')');
    }
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
    
    $searchValue = (bool)$this->criterias->get($field);
    
    if ( $force === false && $searchValue === false ){
      return false;
    }
    
    return $this->dbo->qn($prefix.$field).' = '.($searchValue?'1':'0');
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
    
    $searchValue = (int)$this->criterias->get($field);
    
    if ( $force === false && $searchValue === 0 ){
      return false;
    }
    
    return $this->dbo->qn($prefix.$field).' = '.$searchValue;
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
    
    $stype  = $this->criterias->get('stype', 'contains');
    $searchValue = str_replace("'", "\'", $searchValue);
    
    if ( $stype === 'strict' ){
      return $this->dbo->qn($prefix.$field).' LIKE '.$this->dbo->Quote($searchValue);
    }
    
    if ( $stype === 'startswith' ){
      return $this->dbo->qn($prefix.$field).' LIKE'.$this->dbo->Quote($searchValue.'%');
    }
    
    if ( $stype === 'endswith' ){
      return $this->dbo->qn($prefix.$field).' LIKE '.$this->dbo->Quote('%'.$searchValue);
    }
    
    return $this->dbo->qn($prefix.$field).' LIKE '.$this->dbo->Quote('%'.str_replace(' ', '%', $searchValue).'%');
  }
}
