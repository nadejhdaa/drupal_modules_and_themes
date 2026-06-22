<?php

namespace Drupal\demo_layouts\Plugin\Layout;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Layout\LayoutDefault;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
* A very advanced custom layout.
*
* @Layout(
*   id = "demo_layouts_tabs",
*   label = @Translation("Tabs"),
*   category = @Translation("Effects"),
*   path = "layouts/tabs",
*   template = "tabs",
* )
*/

class Tabset extends LayoutDefault {

  const COUNT = 2;

  /**
 * {@inheritdoc}
 */
public function __construct(
  array $configuration,
  $plugin_id,
  $plugin_definition
) {
  parent::__construct($configuration, $plugin_id, $plugin_definition);
  $this->pluginDefinition->setRegions($this->buildRegions());
}


  /**
   * Builds regions from tab configuration.
   *
   * @param array $tabs
   *   Tabs.
   *
   * @return array
   *   Regions.
   */
  protected function buildRegions() : array {
    $regions = [];

    $regions['title'] = [
      'label' => new TranslatableMarkup('title', [], ['context' => 'layout_region']),
    ];

    $tabs = $this->getTabsArray();
    foreach ($tabs as $tab) {
      $name = 'title_' . $tab;
      $label = !empty($this->configuration[$name]) ? $this->configuration[$name] : 'title_' . $tab;
      $tab_key = 'tab_' . $tab;

      $regions[$tab_key] = [
        'label' => new TranslatableMarkup($label, [], ['context' => 'layout_region']),
      ];
    }

    return $regions;
  }


  //
  /**
   * {@inheritDoc}
   */
  // public function build(array $regions) {
  //   $build = parent::build($regions);
  //
  //   $tabs = $this->getTabsArray();
  //
  //   // $regions = $build['#layout']->getRegions();
  //   // $icon_map = $build['#layout']->getIconMap();
  //   //
  //   // foreach ($tabs as $tab) {
  //   //   $name = 'title_' . $tab;
  //   //   $label = !empty($this->configuration[$name]) ? $this->configuration[$name] : 'tab_' . $tab;
  //   //   $tab_key = 'tab_' . $tab;
  //   //
  //   //   $regions[$tab_key] = [
  //   //     'label' => new TranslatableMarkup($label, [], ['context' => 'layout_region']),
  //   //   ];
  //   //   $icon_map[] = [$tab_key];
  //   // }
  //   //
  //   // $build['#layout']->setRegions($regions);
  //   // $build['#layout']->setIconMap($icon_map);
  //
  //   return $build;
  // }

  /**
   * {@inheritDoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $count = $this->getCount();

    $form['count'] = [
      '#type' => 'number',
      '#min' => 2,
      '#step' => 1,
      '#title' => $this->t('Tabs count'),
      '#description' => $this->t('The zero-based index of the item that is active initially. If empty and collapsible is set, none is active initially.'),
      '#default_value' => $count,
    ];

    $tabs = range(1, $count);

    foreach ($tabs as $tab) {
      $title = 'title_' . $tab;

      $form[$title] = [
        '#type' => 'textfield',
        '#title' => $this->t('Title for tab @num', ['@num' => $tab]),
        '#default_value' => !empty($this->configuration[$title]) ? $this->configuration[$title] : '',
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritDoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $this->configuration['count'] = $form_state->getValue('count');
    $tabs = $this->getTabsArray();

    foreach ($tabs as $tab) {
      $title = 'title_' . $tab; dsm($title);
      $this->configuration[$title] = $form_state->getValue($title);
    }
  }

  public function getCount() {
    return !empty($this->configuration['count']) ? $this->configuration['count'] : self::COUNT;
  }

  public function getTabsArray() {
    $count = $this->getCount();
    return range(1, $count);
  }

}
