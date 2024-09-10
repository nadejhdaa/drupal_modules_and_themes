<?php

/**
 * @file
 * Contains test_log.page.inc.
 *
 * Page callback for test log entities.
 */

use Drupal\Core\Render\Element;

/**
 * Prepares variables for test log templates.
 *
 * Default template: test_log.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - elements: An associative array containing the user information and any
 *   - attributes: HTML attributes for the containing element.
 */
function template_preprocess_test_log(array &$variables) {
  // Fetch testLog Entity Object.
  $test_log = $variables['elements']['#test_log'];

  // Helpful $content variable for templates.
  foreach (Element::children($variables['elements']) as $key) {
    $variables['content'][$key] = $variables['elements'][$key];
  }
}
