<?php
declare(strict_types=1);
/**
 * @author Ayyobro
 * @version 1.0
 */

namespace Ayyobro\DoctrineToMermaid;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * Generates Mermaid ER diagrams from Doctrine entity metadata.
 */
class MermaidDiagramGenerator
{
    /** @var string[] List of fully-qualified entity class names already processed */
    private array $visitedEntities = [];

    /** @var array<string, ClassMetadata> Cached metadata for entities */
    private array $metadataCache = [];

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * Generate a Mermaid ER diagram from a list of entity classes.
     *
     * @param string[] $entityClasses Array of fully-qualified entity class names.
     * @return string Mermaid ER diagram text.
     */
    public function generate(array $entityClasses): string
    {
        $diagram = "erDiagram\n\n";

        foreach ($entityClasses as $class) {
            $this->generateDiagramRecursively($class, $diagram);
        }

        return $diagram;
    }

    /**
     * Recursively generates diagram nodes and relationships for the given entity.
     *
     * @param string $class Fully-qualified entity class name.
     * @param string $diagram Diagram text reference to append to.
     */
    private function generateDiagramRecursively(string $class, string &$diagram): void
    {
        if (in_array($class, $this->visitedEntities, true)) {
            return;
        }

        $this->visitedEntities[] = $class;

        $diagram .= $this->generateEntityDiagram($class);
        $diagram .= $this->generateEntityRelationships($class);
    }

    /**
     * Generates a diagram node for an entity.
     *
     * @param string $class Fully-qualified entity class name.
     * @return string Diagram node representation.
     */
    private function generateEntityDiagram(string $class): string
    {
        $metadata = $this->getMetadata($class);

        $tableName = $this->sanitize($metadata->getTableName());
        $diagram = "    {$tableName} {\n";

        // Get identifiers for marking primary keys.
        $identifiers = $metadata->getIdentifierFieldNames();

        // Sort fields for consistent output.
        $fields = $metadata->getFieldNames();
        sort($fields);

        foreach ($fields as $field) {
            $sanitizedField = $this->sanitize($field);
            $type = $metadata->getTypeOfField($field);
            // Mark primary key fields with (PK)
            $pkMarker = in_array($field, $identifiers, true) ? " (PK)" : "";
            $diagram .= "        {$sanitizedField}{$pkMarker} : {$type}\n";
        }

        $diagram .= "    }\n\n";

        return $diagram;
    }

    /**
     * Generates diagram relationships for an entity.
     *
     * @param string $class Fully-qualified entity class name.
     * @return string Diagram relationships representation.
     */
    private function generateEntityRelationships(string $class): string
    {
        $metadata = $this->getMetadata($class);
        $relationships = "";

        // Sort association mappings for consistent output.
        $associationMappings = $metadata->getAssociationMappings();
        ksort($associationMappings);

        foreach ($associationMappings as $field => $association) {
            // Process only the owning side to avoid duplicate relationships.
            if (isset($association['isOwningSide']) && !$association['isOwningSide']) {
                continue;
            }

            $sanitizedField = $this->sanitize($field);
            $sourceTable = $this->sanitize($metadata->getTableName());
            $targetMetadata = $this->getMetadata($association['targetEntity']);
            $targetTable = $this->sanitize($targetMetadata->getTableName());
            $relationType = $this->getMermaidRelationType($association['type']);

            // If a many-to-many association has a join table, include its name.
            $label = $sanitizedField;
            if (
                $association['type'] === ClassMetadataInfo::MANY_TO_MANY &&
                isset($association['joinTable']['name'])
            ) {
                $joinTableName = $this->sanitize($association['joinTable']['name']);
                $label .= " ({$joinTableName})";
            }

            $relationships .= "    {$sourceTable} {$relationType} {$targetTable} : \"{$label}\"\n";

            // Recursively process the target entity.
            $this->generateDiagramRecursively($association['targetEntity'], $relationships);
        }

        return $relationships;
    }

    /**
     * Returns the Mermaid relationship notation based on the Doctrine association type.
     *
     * @param int $type Doctrine association type constant.
     * @return string Mermaid relationship notation.
     */
    private function getMermaidRelationType(int $type): string
    {
        return match ($type) {
            ClassMetadataInfo::ONE_TO_ONE   => '||--||',
            ClassMetadataInfo::ONE_TO_MANY  => '||--o{',
            ClassMetadataInfo::MANY_TO_ONE  => 'o{--||',
            ClassMetadataInfo::MANY_TO_MANY => 'o{--o{',
            default                         => '--',
        };
    }

    /**
     * Retrieves and caches the metadata for a given entity class.
     *
     * @param string $class Fully-qualified entity class name.
     * @return ClassMetadata The metadata for the entity.
     */
    private function getMetadata(string $class): ClassMetadata
    {
        if (!isset($this->metadataCache[$class])) {
            $this->metadataCache[$class] = $this->entityManager->getClassMetadata($class);
        }

        return $this->metadataCache[$class];
    }

    /**
     * Sanitizes a name to be used in the diagram (e.g., replaces dots with underscores).
     * Because mermaid doesn't like dots...
     *
     * @param string $name The name to sanitize.
     * @return string The sanitized name.
     */
    private function sanitize(string $name): string
    {
        return str_replace('.', '_', $name);
    }
}

