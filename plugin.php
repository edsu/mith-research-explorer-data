<?php
/*
 * Plugin Name: mith-research-explorer-data
 * Plugin URI: https://github.com/mith-umd/mith-research-explorer-data/
 * Description: MITH Research Explorer Data
 * Version: 0.1
 * Author: Ed Summers
 * License: MIT
 */

require(dirname(__FILE__) . '/../../../wp-config.php');

function projects() {
  global $wpdb;

  $q = "
    SELECT 
      wp_posts.id AS id,
      wp_posts.post_title AS title,
      wp_posts.post_name AS name,
      wp_posts.post_excerpt AS description
    FROM wp_posts
    WHERE wp_posts.post_type = 'mith_research'
    ";

  foreach ($wpdb->get_results($q, ARRAY_A) as $project) {
    $project = $project + project_metadata($project["id"]);
    $project = $project + project_terms($project["id"]);
    $projects[] = $project;
  }

  return $projects;
}


function project_metadata($project_id) {
  global $wpdb;

  $q = "
    SELECT meta_key, meta_value
    FROM wp_postmeta
    WHERE post_id = %d
    ";
  $results = array();
  foreach ($wpdb->get_results($wpdb->prepare($q, $project_id), ARRAY_A) as $r) {
    $k = $r["meta_key"];
    $v = $r["meta_value"];

    if (!preg_match('/^research_/', $k)) {
      continue;
    }
    else if ($v and $v != "null") {
      $results[$k] = $v;
    }
  }
  
  return $results;
}


function project_terms($project_id) {
  global $wpdb;

  $q = "
    SELECT wp_terms.name AS name,
      wp_term_taxonomy.taxonomy AS taxonomy
    FROM wp_term_relationships, wp_term_taxonomy, wp_terms
    WHERE 
      wp_term_relationships.object_id = %s
      AND wp_term_relationships.term_taxonomy_id = wp_term_taxonomy.term_taxonomy_id
      AND wp_term_taxonomy.term_id = wp_terms.term_id
      ";

  $results = array();
  foreach ($wpdb->get_results($wpdb->prepare($q, $project_id), ARRAY_A) as $r) {
    preg_match('/^mith_(.+)/', $r['taxonomy'], $m);
    if ($m[1]) {
      $results[$m[1]][] = $r['name'];
    }
  }

  return $results;
}


function taxonomy() {
  global $wpdb;

  $q = "
    SELECT wp_terms.term_id, wp_terms.name, wp_terms.slug
    FROM wp_terms, wp_term_taxonomy
    WHERE 
      wp_terms.term_id = wp_term_taxonomy.term_id
      AND wp_term_taxonomy.taxonomy IN ('mith_topic')
    ";
  $terms = array();
  foreach ($wpdb->get_results($q, ARRAY_A) as $term) {
    $terms[$term["name"]] = array(
      "slug" => $term["slug"],
      "broader" => array(),
      "narrower" => array()
    );
  }

  $q = "
    SELECT wp_term_taxonomy.taxonomy AS taxonomy, 
    wp_term_taxonomy.description AS description, 
      terms.name AS term_name,
      parent_terms.name AS parent_term
    FROM wp_term_taxonomy
    JOIN wp_terms AS terms ON wp_term_taxonomy.term_id = terms.term_id
    LEFT JOIN wp_terms AS parent_terms ON wp_term_taxonomy.parent = parent_terms.term_id
    ";

  foreach ($wpdb->get_results($q, ARRAY_A) as $taxon) {
    $term_name = $taxon["term_name"];
    if (! array_key_exists($term_name, $terms)) {
      continue;
    }
    $terms[$term_name]["taxonomy"] = $taxon["taxonomy"];
    if ($taxon["parent_term"]) {
      $broader = $taxon["parent_term"];
      $terms[$term_name]["broader"][] = $broader;
      $terms[$broader]["narrower"][] = $taxon["term_name"];
    }
  }

  return $terms;
}


function main() {
  if ($_REQUEST["action"] == "taxonomy") {
    $data = taxonomy(); 
  } else {
    $data = projects();
  }

  header("Content-Type: application/json");
  echo json_encode($data);
}


if (!count(debug_backtrace())) {
  main();
}


?>
