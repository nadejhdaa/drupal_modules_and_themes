<?php

namespace Drupal\kc_svg\TwigExtension;

use Twig\TwigFunction;
use Twig\Extension\AbstractExtension;


class KcSvg extends AbstractExtension {

 /**
  * List the custom Twig fuctions.
  *
  * @return array
  */
  public function getFunctions() {
    return [
      new TwigFunction('icon', array($this, 'getInlineSvg')),
    ];
  }


  /**
   * Get the name of the service listed in svgy.services.yml
   *
   * @return string
   */
  public function getName() {
    return "kc_svg.twig.extension";
  }

  /**
   * Callback for the icon() Twig function.
   *
   * @return array
   */
  public static function getInlineSvg($path, $classes = []) {
    $svg_string = file_get_contents($path);

    if (!empty($svg_string)) {
      $svg = simplexml_load_string($svg_string);

      if (!empty($classes)) {
        $class = implode(' ', $classes);
      }

      $svg->addAttribute('class', $class);
      $svg_string = str_replace('<?xml version=\"1.0\"?>\n', '', $svg->asXML());

      return [
        '#type' => 'inline_template',
        '#template' => '{{ svg|raw }}',
        '#context' => [
          'svg' => $svg_string,
        ],
      ];
    }

    return '';
  }
};
