<?php

/**
 * @file
 * Contains test_redirect.page.inc.
 *
 * Page callback for Test redirect entities.
 */

use Drupal\Core\Render\Element;

/**
 * Prepares variables for Test redirect templates.
 *
 * Default template: test_redirect.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - elements: An associative array containing the user information and any
 *   - attributes: HTML attributes for the containing element.
 */
function template_preprocess_test_redirect(array &$variables) {
  // Fetch TestRedirect Entity Object.
  $test_redirect = $variables['elements']['#test_redirect'];
  // Helpful $content variable for templates.
  foreach (Element::children($variables['elements']) as $key) {
    $variables['content'][$key] = $variables['elements'][$key];
  }
}
