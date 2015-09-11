<?php
	if (!IN_DREAMFORGERY) die();
	class Database {
		static private $dbtype;
		static private $dblink;

		static public function connect($host=CMS_DB_HOST, $user=CMS_DB_USER, $password=CMS_DB_PASS, $database=CMS_DB_NAME, $dbtype='mysql') {
			
			self::$dbtype = $dbtype;

			if (self::$dbtype=='mysql') {
				self::$dblink = new mysqli($host, $user, $password, $database);
			}

			return self::$dblink;
		}

		static public function query($query_string) {
			if (self::$dbtype == 'mysql') {
				$ret = self::$dblink->query($query_string);
				if ($ret == false) elog(self::$dblink->error);
				return $ret;
			}
		}

		static public function queryFile($filename) {
			$ret = self::query(file_get_contents($filename));
			if ($ret == false) elog('mysql error');
			return $ret;
		}

		static public function param($param) {
			if (self::$dbtype == 'mysql') {
				return self::$dblink->real_escape_string($param);
			}
		}

		static public function table($table_name) {
			return CMS_DB_PREFIX.$table_name;
		}

		static public function queryResults($query_string) {
			$ret = self::query($query_string);
			if ($ret->num_rows>0) {
				$results = array();
				while ($data = $ret->fetch_assoc()) {
					$results[] = $data;
				}
				return $results;
			} else {
				return false;
			}
		}

	}
	class SearchInterface {
		
		private $p_fields;
		private $p_tables;
		private $p_leftJoins;
		private $p_havings;
		private $p_searchFields;
		private $p_current_page, $p_per_page;
		private $p_relRanks;
		private $p_searchTerms;
		private $p_recSpec;
		private $p_debug;
		
		function __construct() {
			$this->p_fields = array();
			$this->p_tables = array();
			$this->p_leftJoins = array();
			$this->p_havings = array();
			$this->p_searchFields = array();
			$this->p_current_page = 1;
			$this->p_per_page = 10;
			$this->p_relRanks = array();
			$this->p_searchTerms = array();
			$this->p_recSpec = array();
			$this->p_debug = false;
		}
		
		/*********
		 * debug(True / False): Display Relevancy ranking in addition to select fields
		 */
		public function debug($switch=true) {
			$this->p_debug = $switch;
		}
		
		/*********
		 * addFields(array(field => alias), [...])
		 */
		public function addFields($fields) {
			if (!is_array($fields)) throw new Exception("SearchInterface->addFields(array(field => alias), [...]) wasn't provided an array");
			foreach($fields as $key => $value) {
				$this->p_fields[$value] = $key;
			}
		}
		
		/*********
		 * addTables(array(table => alias), [...])
		 */
		public function addTables($tables) {
			if (!is_array($tables)) throw new Exception("SearchInterface->addTables(array(table => alias), [...]) wasn't provided an array");
			foreach($tables as $key => $value) {
				$this->p_tables[$value] = $key;
			}
		}

		/*********
		 * addLeftJoins(array(Table Field / Alias => SQL Condition), [...])
		 */
		public function addLeftJoins($joins) {
			if (!is_array($joins)) throw new Exception("SearchInterface->addLeftJoins(array(Table Field / Alias => SQL Condition), [...]) wasn't provided an array");
			foreach($joins as $key => $value) {
				$this->p_leftJoins[$key] = $value;
			}
		}

		/*********
		 * addHavings(array(Table Field / Alias => SQL Condition), [...])
		 */
		public function addHavings($having) {
			if (!is_array($having)) throw new Exception("SearchInterface->addHavings(array(Table Alias => SQL Condition), [...]) wasn't provided an array");
			foreach($having as $key => $value) {
				$this->p_havings[$key] = $value;
			}
		}

		/*********
		 * addSearchField(array(Field Alias => Priority Score), [...])
		 * Priority Score is higher on top
		 */
		public function addSearchField($searchFields) {
			if (!is_array($searchFields)) throw new Exception("SearchInterface->addSearchField(array(Table Field / Alias => Priority Score), [...]) wasn't provided an array");
			foreach($searchFields as $key => $value) {
				$this->p_searchFields[$key] = $value;
			}
		}

		/*********
		 * setPagination(Current Page, Items Per Page)
		 */
		public function setPagination($current_page, $per_page) {
			if (!is_numeric($current_page)) throw new Exception("SearchInterface->setPagination(Current Page, Items Per Page): 'Current page' is not numeric!");
			if (!is_numeric($per_page)) throw new Exception("SearchInterface->setPagination(Current Page, Items Per Page): 'Items per page' is not numeric!");
			$this->p_current_page = floor($current_page);
			$this->p_per_page = floor($per_page);
		}
		
		/*********
		 * addRelevancyRanking(
		 *	array(
		 *		Rank Algorithm['NTerms' / 'MaxField' / 'Glom' / 'Exact' / 'Static']
		 *			=>
		 *		Rank Algorithm['NTerms' / 'MaxField' / 'Glom' / 'Exact' / Static: [Field Alias ASC / Field Alias DESC]]
		 *		, [...]
		 *	)
		 */
		public function addRelevancyRanking($relRank) {
			if (!is_array($relRank)) throw new Exception("SearchInterface->addRelevancyRanking(array(Rank Algorithm['NTerms' / 'MaxField' / 'Glom' / 'Exact' / 'Static'] => Rank Algorithm['NTerms' / 'MaxField' / 'Glom' / 'Exact' / Static: [Field Alias ASC / Field Alias DESC]]), [...]) wasn't provided an array");
			foreach($relRank as $key => $value) {
				$this->p_relRanks[] = $value;
			}
		}
		
		/*********
		 * setSearchTerms(String Search Terms)
		 * Example: 'web security'
		 */
		public function setSearchTerms($searchTerms) {
			$this->p_searchTerms = explode('+', $searchTerms);
		}
		
		/*********
		 * buildQuery(): Generate SQL for query
		 */
		public function buildQuery($countQuery=false) {
			$ret = '';
			$ret .= 'SELECT ';

			if (!$countQuery) {
				if ($this->p_debug) {
					$relRanks = array();
					foreach ($this->p_relRanks as $relRank) {
						switch (strtolower($relRank)) {
							case 'nterms':
								$relRanks[] = $this->buildNTerms().' nterms';
								break;
							case 'maxfield':
								$relRanks[] = $this->buildMaxField().' maxfield';
								break;
							case 'glom':
								$relRanks[] = $this->buildGlom().' glom';
								break;
							case 'exact':
								$relRanks[] = $this->buildExact().' exact';
								break;
							default:
						}
					}

					$ret .= implode(', ', $relRanks);
					$ret .= ", ";
				}

				
				$selectFields = array();
				foreach ($this->p_fields as $alias => $field) {
					$selectFields[] = $field.' '.$alias;
				}
				$ret .= implode(', ', $selectFields)."\n";
			} else {
				// count query
				$ret .= 'COUNT(*) totalCount ';
				$havings = array();
				$i=0;
				foreach ($this->p_havings as $alias => $condition) {
					$i++;
					$field = (isset($this->p_fields[$alias])) ? $this->p_fields[$alias] : $alias; // can lookup by alias
					$ret .= ', '.$field.' havingCountTmp'.$i;
				}
			}
			$ret .= ' FROM ';
			foreach ($this->p_tables as $alias => $table) {
				$ret .= $table.' '.$alias.' ';
				break;
			}
			$ret .= "\n";
			foreach ($this->p_leftJoins as $alias => $join) {
				$ret .= 'LEFT JOIN '.$this->p_tables[$alias].' '.$alias.' ON '.$join.' ';
			}
			$ret .= "\n";

			$ret .= 'WHERE ';
			$searchArray = array();
			foreach ($this->p_searchFields as $field => $priority) {
				if (isset($this->p_fields[$field])) $field = $this->p_fields[$field]; // can lookup by alias
				$termsList = array();
				foreach ($this->p_searchTerms as $term) {
					$termsList[] = $field." LIKE '%".Database::param($term)."%'";
				}
				$searchArray[] = '('.implode(' OR ', $termsList).')';
			}
			$ret .= '('.implode(' OR ', $searchArray).') '."\n";

			$ret .= 'HAVING ';
			
			$havings = array();
			foreach ($this->p_havings as $alias => $condition) {
				$field = (isset($this->p_fields[$alias])) ? $this->p_fields[$alias] : $alias; // can lookup by alias
				$havings[] = '('.$field.' '.$condition.')';
			}
			$ret .= implode(' AND ', $havings).' '."\n";

			if (!$countQuery) {
				$ret .= 'ORDER BY ';

				/*******************
				 * - NTerms: Number of distinct terms matched DESC order, ex: 3/3, 2/3, 1/3
				 * - MaxField: Field priority score, ex: title 2, description 1, 
				 * - Glom: Single Field match priority
				 * - Exact: Score: 2: Complete text match of user query, 1: Else
				 * - Static: Sort by date DESC
				 */
				$relRanks = array();
				foreach ($this->p_relRanks as $relRank) {
					switch (strtolower($relRank)) {
						case 'nterms':
							$relRanks[] = $this->buildNTerms().' DESC';
							break;
						case 'maxfield':
							$relRanks[] = $this->buildMaxField().' DESC';
							break;
						case 'glom':
							$relRanks[] = $this->buildGlom().' DESC';
							break;
						case 'exact':
							$relRanks[] = $this->buildExact().' DESC';
							break;
						default:
							$relRanks[] = $relRank;
					}
				}

				$ret .= implode(', ', $relRanks);
				
				$index = (floor($this->p_current_page) * floor($this->p_per_page)) - floor($this->p_per_page);
				if ($index < 0) $index = 0;
				
				$ret .= " LIMIT ".$index.", ".$this->p_per_page.";";
			}			
			return $ret;
		}

		private function buildNTerms($relRank='nterms') {
			$searchArray = array();
			foreach ($this->p_searchTerms as $term) {
				$fieldList = array();
				foreach ($this->p_searchFields as $field => $priority) {
					if (isset($this->p_fields[$field])) $field = $this->p_fields[$field]; // can lookup by alias
					$fieldList[] = '('.$field." LIKE '%".Database::param($term)."%')";
				}
				$subquery = '(SELECT COUNT(*) FROM (SELECT 1) '.$relRank;
				$subquery .= ' WHERE ('.implode(' OR ', $fieldList).') LIMIT 1)';
				$searchArray[] = $subquery."\n";
			}
			return '(SELECT ('.implode(' + ', $searchArray).'))'."\n";
		}

		private function buildMaxField($relRank='maxfield') {
			$searchArray = array();
			$i=0;
			foreach ($this->p_searchFields as $field => $priority) {
				$i++;
				$fieldList = array();
				foreach ($this->p_searchTerms as $term) {
					if (isset($this->p_fields[$field])) $field = $this->p_fields[$field]; // can lookup by alias
					$fieldList[] = '('.$field." LIKE '%".Database::param($term)."%')";
				}
				$subquery = "\n".'(SELECT COALESCE((SELECT '.$priority.' FROM (SELECT 1) '.$relRank.$i.' WHERE ('.implode(' OR ', $fieldList).') LIMIT 1), 0))';
				$searchArray[] = $subquery;
			}
			return '(SELECT GREATEST('.implode(', ', $searchArray).'))'."\n";
		}

		private function buildGlom($relRank='glom') {
			$searchArray = array();
			$i=0;
			foreach ($this->p_searchFields as $field => $priority) {
				$i++;
				$fieldList = array();
				foreach ($this->p_searchTerms as $term) {
					if (isset($this->p_fields[$field])) $field = $this->p_fields[$field]; // can lookup by alias
					$fieldList[] = '('.$field." LIKE '%".Database::param($term)."%')";
				}
				$subquery = "\n".'(SELECT COALESCE((SELECT 1 FROM (SELECT 1) '.$relRank.$i.' WHERE ('.implode(' AND ', $fieldList).') LIMIT 1), 0))';
				$searchArray[] = $subquery;
			}
			return '(SELECT GREATEST('.implode(', ', $searchArray).'))'."\n";
		}

		private function buildExact($relRank='exact') {
			$searchArray = array();
			$i=0;
			foreach ($this->p_searchFields as $field => $priority) {
				$i++;
				$exactTerms = array();
				foreach ($this->p_searchTerms as $term) {
					$exactTerms[] = Database::param($term);
				}
				if (isset($this->p_fields[$field])) $field = $this->p_fields[$field]; // can lookup by alias
				$subquery = "\n".'(SELECT COALESCE((SELECT 1 FROM (SELECT 1) '.$relRank.$i.' WHERE ('.$field." LIKE '%".implode(' ', $exactTerms)."%') LIMIT 1), 0))";
				$searchArray[] = $subquery;
			}
			return '(SELECT GREATEST('.implode(', ', $searchArray).'))'."\n";
		}
		
		public function getPaginationInfo() {
			$sqlQuery = $this->buildQuery(true);	// query count(true)
			$data = Database::queryResults($sqlQuery);
			if ($data) {
				$results = array();
				$results['pagination'] = array();
				$total_count = $data[0]["totalCount"];
				$results['pagination']['pages'] = ceil($total_count / $this->p_per_page);
				if ($this->p_current_page - 1 >= 1) {
					$results['pagination']['previous'] = $this->p_current_page - 1;
				} else {
					$results['pagination']['previous'] = -1;
				}
				if ($this->p_current_page + 1 <= $results['pagination']['pages']) {
					$results['pagination']['next'] = $this->p_current_page + 1;
				} else {
					$results['pagination']['next'] = -1;
				}

				if ($this->p_current_page < 1) return 0;
				if ($this->p_current_page > $results['pagination']['pages']) return 0;
				
				return $results['pagination'];
			}
			return 0;
		}
		
	}	
?>