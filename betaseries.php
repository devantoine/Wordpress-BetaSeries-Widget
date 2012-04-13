<?php
/*
	Plugin Name: BetaSeries
	Plugin URI: http://antoinedescamps.fr
	Description: Widget for displaying latest viewed TV shows from www.betaseries.com
	Author: Antoine Descamps
	Version: 1.0
	Author URI: http://antoinedescamps.fr
*/

class BetaSeries_Widget extends WP_Widget
{
	public function __construct()
	{
		$widget_ops = array('classname' => 'BetaSeries_Widget', 'description' => 'Widget for displaying latest viewed TV shows');
		parent::__construct('BetaSeries_Widget', __('BetaSeries', 'betaseries_widget'), $widget_ops);
	}
	
	private function actionType($string) 
	{
		$explodedString = explode(" ", $string);
		
		if (in_array ("ajout&eacute;", $explodedString))		return "add";
		elseif (in_array ("regarder", $explodedString))			return "watch";
		elseif (in_array ("rejet&eacute;", $explodedString))	return "reject";
		else													return "archive";
	}

	private function parseUrls($string) 
	{
		return preg_replace('/<a href="/', '<a href="http://betaseries.com', $string);
	}
	
	public function form($instance)
	{
		$instance = wp_parse_args((array) $instance, array('title' => '', 'pseudo' => '', 'max_results' => '', 'cache_time' => ''));
		
		echo '<p><label for="' . $this->get_field_id('title') . '">' . __('Titre du widget&nbsp;:', 'betaseries_widget') . '</label>';
		echo '<input type="text" name="' . $this->get_field_name('title') . '" id="' . $this->get_field_id('title') . '" value="' . $instance['title'] . '"></p>';
			
		echo '<p><label for="' . $this->get_field_id('pseudo') . '">' . __('Pseudo du compte&nbsp;:', 'betaseries_widget') . '</label>';
		echo '<input type="text" name="' . $this->get_field_name('pseudo') . '" id="' . $this->get_field_id('pseudo') . '" value="' . $instance['pseudo'] . '"></p>';
		
		echo '<p><label for="' . $this->get_field_id('max_results') . '">' . __('Nombre de résultats à afficher&nbsp;:', 'betaseries_widget') . '</label>';
		echo '<input type="number" name="' . $this->get_field_name('max_results') . '" id="' . $this->get_field_id('max_results') . '" min="0" value="' . esc_attr($instance['max_results']) . '"></p>';
		
		echo '<p><label for="' . $this->get_field_id('cache_time') . '">' . __('Durée de vie du cache&nbsp;:', 'betaseries_widget') . '</label>';
		echo '<input type="number" name="' . $this->get_field_name('cache_time') . '" id="' . $this->get_field_id('cache_time') . '" min="0" value="' . esc_attr($instance['cache_time']) . '"></p>';
	}

	public function update($new_instance, $old_instance)
	{
		$instance = array();
		$instance['title'] = !empty($new_instance['title']) ? strip_tags($new_instance['title']) : __('Dernières séries', 'betaseries_widget');
		$instance['pseudo'] = strip_tags($new_instance['pseudo']);
		$instance['max_results'] = strip_tags($new_instance['max_results']);
		$instance['cache_time'] = strip_tags($new_instance['cache_time']);

		return $instance;
	}
	
	private function getRssContent($instance)
	{
		$feed = "http://www.betaseries.com/rss/timeline/" . $instance['pseudo'];
		$file_path = WP_PLUGIN_DIR . '/betaseries/cache.data';
		
		if (file_exists($file_path)) {
			$data = unserialize(file_get_contents($file_path));
			if ($data['timestamp'] > time() - $instance['cache_time'] * 60) {
				$betaseries_result = $data['betaseries_result'];
			}
		}
		
		if (!$betaseries_result) { // cache doesn't exist or is older than the limit
			$betaseries_result = @file_get_contents($feed);
			
			if ($betaseries_result !== false) {
				$data = array ('betaseries_result' => $betaseries_result, 'timestamp' => time());
				file_put_contents($file_path, serialize($data));
			}
		}
		
		return $betaseries_result;
	}

	public function widget($args, $instance)
	{
		extract($args);
		
		echo $before_widget;
		
		echo $before_title . $instance['title'] . $after_title;
		
		$dom = new DomDocument();
		if (true === @$dom->loadXML($this->getRssContent($instance))) {
		
			echo '<ul>';
			
			$entries = $dom->getElementsByTagName("content");
			
			$imgUrl = WP_PLUGIN_URL . '/betaseries/images/';
			
			$i = 0;
			foreach ($entries as $entry) {
				echo '<li>';
				echo '<img src="' . $imgUrl . $this->actionType($entry->nodeValue) . '.png">';
				echo $this->parseUrls($entry->nodeValue);
				echo '</li>';
				
				if (++$i == $instance['max_results']) {
					break;
				}
			}
			
			echo '</ul>';
			
		} else {
			_e('Le flux ne peut être lu.', 'betaseries_widget');
		}
		
		echo $after_widget;
	}
}
add_action('widgets_init', create_function('', 'register_widget("BetaSeries_Widget");'));
	