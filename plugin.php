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
  $people = people();

  $q = "
    SELECT 
      wp_posts.id AS id,
      wp_posts.post_title AS title,
      wp_posts.post_name AS slug,
      wp_posts.post_excerpt AS description
    FROM wp_posts
    WHERE wp_posts.post_type = 'mith_research'
    ";

  foreach ($wpdb->get_results($q, ARRAY_A) as $project) {
    $project = $project + project_metadata($project["id"], $people);
    $project = $project + project_terms($project["id"]);
    $projects[] = $project;
  }

  return $projects;
}


function project_metadata($project_id, $people) {
  global $wpdb;

  $q = "
    SELECT meta_key, meta_value
    FROM wp_postmeta
    WHERE post_id = %d
    ";

  $start_year = null;
  $start_month = "01";
  $end_year = null;
  $end_month = "12";

  $results = array();
  $project_people = array();
  $links = array();

  foreach ($wpdb->get_results($wpdb->prepare($q, $project_id), ARRAY_A) as $r) {
    $k = $r["meta_key"];
    $v = $r["meta_value"];

    if (! $v or $v == "null") {
      continue;
    }

    if (preg_match('/^research_people_(int_\d+)_research_person$/', $k, $m)) {
      $project_people[$m[1]] = array(
        "name" => $people[$v],
        "affiliation" => "University of Maryland"
      );
    } else if (preg_match('/^research_people_(ext_\d+)_research_person_(name|affiliation|department)/', $k, $m)) {
      if (! $project_people[$m[1]]) {
        $project_people[$m[1]] = array("name" => null, "affiliation" => "University of Maryland, College Park");
      }
      $project_people[$m[1]][$m[2]] = $v;
    } else if ($k == "_thumbnail_id") {
      $results["thumbnail"] = get_thumbnail($v);
    } else if ($k == "research_website_url") {
      $results["website"] = "http://" . $v;
    } else if (preg_match('/^research_links_ext_(\d+)_research_link_(url|title)$/', $k, $m)) {
      if (! $links[$m[1]]) {
        $links[$m[1]] = array("title" => "Website");
      }
      $links[$m[1]][$m[2]] = $v;
    } else if ($k == "research_start_mth") {
      $start_month = $v;
    } else if ($k == "research_start_yr") {
      $start_year = $v;
    } else if ($k == "research_end_mth") {
      $end_month = $v;
    } else if ($k == "research_end_yr") {
      $end_year = $v;
    }
  }

  if ($start_year) {
    $results["start"] = sprintf("%s-%s-01", $start_year, $start_month);
  } else {
    $results["start"] = null;
  }

  if ($end_year) {
    $results["end"] = sprintf("%s-%s-01", $end_year, $end_month);
  } else {
    $results["end"] = null;
  }

  $results["member"] = array_values($project_people);
  $results["link"] = array_values($links);
  
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


function people() {
  global $wpdb;

  $q = "
    SELECT id, post_title AS name
    FROM wp_posts 
    WHERE post_type = 'mith_person'
    ";
  $people = array();
  foreach ($wpdb->get_results($q, ARRAY_A) as $p) {
    $people[$p["id"]] = $p["name"];
  }

  return $people;
}


function get_thumbnail($thumb_id) {
  global $wpdb;

  $q = "
    SELECT guid
    FROM wp_posts
    WHERE post_type = 'attachment'
    AND id = %d
    ";
  
  return $wpdb->get_var($wpdb->prepare($q, $thumb_id));
}


function topics() {
  $t = taxonomy('mith_topic');
  $t[] = array(
    "name" => "Other",
    "slug" => "other",
    "broader" => [],
    "narrower" => []
  );

  return $t;
}


function types() {
  $t = taxonomy('mith_research_type');
  $t[] = array(
    "name" => "Other",
    "slug" => "other",
    "broader" => [],
    "narrower" => []
  );

  return $t;
}


function taxonomy($taxonomy) {
  global $wpdb;

  $q = "
    SELECT wp_terms.term_id, wp_terms.name, wp_terms.slug
    FROM wp_terms, wp_term_taxonomy
    WHERE 
      wp_terms.term_id = wp_term_taxonomy.term_id
      AND wp_term_taxonomy.taxonomy IN (%s)
    ";
  $terms = array();
  foreach ($wpdb->get_results($wpdb->prepare($q, $taxonomy), ARRAY_A) as $term) {
    $terms[$term["name"]] = array(
      "slug" => $term["slug"],
      "broader" => array(),
      "narrower" => array()
    );
  }

  $q = "
    SELECT wp_term_taxonomy.description AS description, 
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
    if ($taxon["parent_term"]) {
      $broader = $taxon["parent_term"];
      $terms[$term_name]["broader"][] = $broader;
      $terms[$broader]["narrower"][] = $taxon["term_name"];
    }
  }

  $term_list = [];
  foreach ($terms as $name => $term) {
    $term['name'] = $name;
    $term_list[] = $term; 
  }

  return $term_list;
}


function main() {
  $action = $_REQUEST["action"];
  if ($action == "topics") {
    $data = topics(); 
  } else if ($action == "types") {
    $data = types();
  } else if ($action == "people") {
    $data = people();
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
