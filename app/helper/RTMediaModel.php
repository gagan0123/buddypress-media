<?php

/**
 * Description of BPMediaModel
 *
 * @author joshua
 */
class RTMediaModel extends RTDBModel {

    function __construct() {
        parent::__construct('rtm_media');
		$this->meta_table_name = "rtm_media_meta";
    }

	/**
	 *
	 * @param type $name
	 * @param type $arguments
	 * @return type
	 */
    function __call($name, $arguments) {
        $result = parent::__call($name, $arguments);
        if (!$result['result']) {
            $result['result'] = $this->populate_results_fallback($name, $arguments);
        }
        return $result;
    }

	/**
	 *
	 * @global type $wpdb
	 * @param type $columns
	 * @param type $offset
	 * @param type $per_page
	 * @param type $order_by
	 * @return type
	 */
	function get($columns, $offset=false, $per_page=false, $order_by= 'media_id desc') {
        $select = "SELECT * FROM {$this->table_name}";
        $join = "" ;
        $where = " where 2=2 " ;
        $temp = 65;
        foreach ($columns as $colname => $colvalue) {
            if(strtolower($colname) =="meta_query"){
                foreach($colvalue as $meta_query){
                    if(!isset($meta_query["compare"])){
                        $meta_query["compare"] = "=";
                    }
                    $tbl_alias = chr($temp++);
                    $join .= " LEFT JOIN {$this->meta_table_name} {$tbl_alias} ON {$this->table_name}.media_id = {$tbl_alias}.media_id ";
                    $where .= " AND  ({$tbl_alias}.meta_key = '{$meta_query["key"]}' and  {$tbl_alias}.meta_value  {$meta_query["compare"]}  '{$meta_query["value"]}' ) ";
                }
            }else{
				if(is_array($colvalue)) {
					if(!isset($colvalue['compare']))
						$compare = 'IN';
					else
						$compare  = $colvalue['compare'];
					if(!isset($colvalue['value'])){
						$colvalue['value'] = $colvalue;
					}

					$where .= " AND {$this->table_name}.{$colname} {$compare} ('". implode("','", $colvalue['value'])."')";

				} else
					$where .= " AND {$this->table_name}.{$colname} = '{$colvalue}'";
            }
        }
        $sql = $select . $join . $where ;

		$sql .= " ORDER BY {$this->table_name}.$order_by";

		if(is_integer($offset) && is_integer($per_page)) {
			$sql .= ' LIMIT ' . $offset . ',' . $per_page;
		}
        global $wpdb;
        return $wpdb->get_results($sql);
    }

	/**
	 *
	 * @param type $name
	 * @param type $arguments
	 * @return type
	 */
    function populate_results_fallback($name, $arguments) {
        $result['result'] = false;
        if ('get_by_media_id' == $name && isset($arguments[0]) && $arguments[0]) {

            $result['result'][0]->media_id = $arguments[0];

            $post_type = get_post_field('post_type', $arguments[0]);
            if ('attachment' == $post_type) {
                $post_mime_type = explode('/', get_post_field('post_mime_type', $arguments[0]));
                $result['result'][0]->media_type = $post_mime_type[0];
            } elseif ('bp_media_album' == $post_type) {
                $result['result'][0]->media_type = 'bp_media_album';
            } else {
                $result['result'][0]->media_type = false;
            }

            $result['result'][0]->context_id = intval(get_post_meta($arguments[0], 'bp-media-key', true));
            if ($result['result'][0]->context_id > 0)
                $result['result'][0]->context = 'profile';
            else
                $result['result'][0]->context = 'group';

            $result['result'][0]->activity_id = get_post_meta($arguments[0], 'bp_media_child_activity', true);

            $result['result'][0]->privacy = get_post_meta($arguments[0], 'bp_media_privacy', true);
        }
        return $result['result'];
    }

	/**
	 *
	 * @param type $columns
	 * @param type $offset
	 * @param type $per_page
	 * @param type $order_by
	 * @return type
	 */
    function get_media($columns, $offset, $per_page, $order_by = 'media_id desc') {
        if (is_multisite()) {
            $results = $this->get($columns, $offset, $per_page, "blog_id ,".$order_by);
        } else {
            $results = $this->get($columns, $offset, $per_page, $order_by);
        }
        return $results;
    }

    function get_user_albums($author_id, $offset, $per_page, $order_by = 'media_id desc'){
        global $wpdb;
        if (is_multisite() )
            $order_by = "blog_id ,".$order_by;
        $sql = "SELECT * FROM {$this->table_name} WHERE id IN(SELECT DISTINCT (album_id) FROM {$this->table_name} WHERE media_author = $author_id AND album_id IS NOT NULL AND media_type != 'album') OR (media_type = 'album' AND media_author = $author_id)";
        $sql .= " ORDER BY {$this->table_name}.$order_by";

        if(is_integer($offset) && is_integer($per_page)) {
                $sql .= ' LIMIT ' . $offset . ',' . $per_page;
        }

        $results = $wpdb->get_results($sql);
        return $results;
    }
}
?>
