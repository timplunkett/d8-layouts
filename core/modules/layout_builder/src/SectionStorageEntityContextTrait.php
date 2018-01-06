<?php

namespace Drupal\layout_builder;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides contexts for any entity-based section storage.
 */
trait SectionStorageEntityContextTrait {

  /**
   * Produces contexts for the entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being laid out.
   *
   * @return \Drupal\Core\Plugin\Context\ContextInterface[]
   *   The array of context objects.
   */
  protected function getEntityContexts(EntityInterface $entity) {
    $contexts = [];
    // Create a new context for the entity we're currently dealing with.
    $definition = new ContextDefinition("entity:{$entity->getEntityTypeId()}", new TranslatableMarkup('Current @entity', [
      '@entity' => $entity->getEntityType()->getSingularLabel(),
    ]));
    $context = new Context($definition, $entity);
    $contexts['layout_builder.entity'] = $context;
    return $contexts;
  }

}
