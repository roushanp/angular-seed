<?php

namespace Drupal\services_menu\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Psr\Log\LoggerInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;

/**
 * Provides a service resource for menus.
 *
 * @RestResource(
 *   id = "services_menu",
 *   label = @Translation("Services for menus."),
 *   uri_paths = {
 *     "canonical" = "/services/menu/{menu}"
 *   }
 * )
 */
class ServicesMenuResource extends ResourceBase {

  /**
   * The menu tree.
   *
   * @var \Drupal\Core\Menu\MenuLinkTreeInterface
   */
  protected $menuTree;

  /**
   * The url generator service.
   *
   * @var \Drupal\Core\Routing\UrlGeneratorInterface
   */
  protected $urlGenerator;

  /**
   * Constructs a Drupal\rest\Plugin\ResourceBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Menu\MenuLinkTreeInterface $menuTree
   *   The menu tree.
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $urlGenerator
   *   The url generator service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger, MenuLinkTreeInterface $menuTree, UrlGeneratorInterface $urlGenerator) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->menuTree = $menuTree;
    $this->urlGenerator = $urlGenerator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('menu.link_tree'),
      $container->get('url_generator')
    );
  }

  /**
   * Create the menu response.
   *
   * @param string $menu_name
   *   The name of menu to load.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The menu response object.
   */
  public function get($menu_name = NULL) {
    $menu_tree = $this->menuTree;
    $generator = $this->urlGenerator;
    $menu = [];

    // Load the tree based on this set of parameters.
    $tree = $menu_tree->load($menu_name, new MenuTreeParameters());

    // Transform the tree using the manipulators you want.
    $manipulators = [
      // Only show links that are accessible for the current user.
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      // Use the default sorting of menu links.
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    $tree = $menu_tree->transform($tree, $manipulators);

    foreach ($tree as $element) {
      /** @var \Drupal\Core\Menu\MenuLinkInterface $link */
      $link = $element->link;
      $path = $generator->getPathFromRoute($link->getRouteName());

      $menu[$link->getRouteName()]['title'] = $link->getTitle();
      $menu[$link->getRouteName()]['url'] = $path;

      if ($element->subtree) {
        $subtree = $menu_tree->build($element->subtree);

        foreach ($subtree['#items'] as $key => $value) {
          $path = $generator->getPathFromRoute($key);
          $menu[$key]['title'] = $value['title'];
          $menu[$key]['url'] = $path;
          // For getting submenu list.
          foreach ($subtree['#items'][$key]['below'] as $subkey => $subvalue) {
            $path = $generator->getPathFromRoute($subkey);
            $menu[$key]['below'][$subkey]['title'] = $subvalue['title'];
            $menu[$key]['below'][$subkey]['url'] = $path;
          }
        }
      }
    }
    $response = new ResourceResponse($menu);
    $response->addCacheableDependency($menu);
    return $response;
  }

}
