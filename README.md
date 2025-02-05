# Doctrine to Mermaid

[![License: GPL-3.0-or-later](https://img.shields.io/badge/License-GPL%203.0--or--later-blue.svg)](https://www.gnu.org/licenses/gpl-3.0.html)

`ayyobro/doctrine-to-mermaid` is a PHP library that generates Mermaid ER diagrams from your Doctrine entity metadata. It automatically extracts field information, primary keys, and relationships from your entities to create a visual representation of your database schema using Mermaid syntax.

## Features

- **Automatic Extraction:** Reads Doctrine entity metadata to build diagrams.
- **Primary Key Identification:** Marks primary key fields clearly with `(PK)`.
- **Relationship Support:** Handles One-to-One, One-to-Many, Many-to-One, and Many-to-Many relationships.
- **Customizable Output:** Generates clean, human-readable Mermaid text that can be rendered in any Mermaid live editor.

## Installation

Install the package using Composer:

```bash
composer require ayyobro/doctrine-to-mermaid
```

## Usage
You can integrate this package into any PHP project either through a console command or a controller.

### Example: Symfony Console Command
Create a new command (e.g. `src/Command/GenerateMermaidDiagramCommand.php`) that generates a Mermaid diagram from your Doctrine entities:

```php
<?php
// src/Command/GenerateMermaidDiagramCommand.php
namespace App\Command;

use Ayyobro\DoctrineToMermaid\MermaidDiagramGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateMermaidDiagramCommand extends Command
{
    protected static $defaultName = 'app:generate-mermaid-diagram';

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Generates a Mermaid diagram from your Doctrine entities.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Instantiate the MermaidDiagramGenerator with the Entity Manager.
        $generator = new MermaidDiagramGenerator($this->entityManager);

        // Option 1: Specify an array of entity class names manually.
        // $entities = [
        //     \App\Entity\YourFirstEntity::class,
        //     \App\Entity\YourSecondEntity::class,
        // ];

        // Option 2: Automatically retrieve all entity class names from Doctrine.
        $allMetadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $entities = array_map(fn($metadata) => $metadata->getName(), $allMetadata);

        // Generate the Mermaid diagram string.
        $diagram = $generator->generate($entities);

        // Output the generated diagram.
        $output->writeln($diagram);

        return Command::SUCCESS;
    }
}

```

### Example: Symfony Controller

Alternatively, you can generate and display the diagram through a web interface. 
Create a controller (e.g. `src/Controller/MermaidDiagramController.php`) that generates a Mermaid diagram from your Doctrine entities:

```php
<?php
// src/Controller/MermaidController.php
namespace App\Controller;

use Ayyobro\DoctrineToMermaid\MermaidDiagramGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MermaidDiagramController extends AbstractController
{
    #[Route('/mermaid-diagram', name: 'mermaid_diagram', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $generator = new MermaidDiagramGenerator($entityManager);

        // Retrieve all entity class names.
        $allMetadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $entities = array_map(fn($metadata) => $metadata->getName(), $allMetadata);

        $diagram = $generator->generate($entities);

        return new Response(
            '<pre>' . htmlspecialchars($diagram) . '</pre>'
        );
    }
}
```
### Example: Storing into S3 File to Share
You might want to instead put the resulting mermaid diagram into an S3 bucket to share with others. Here is an example of how you might do that:

```php
<?php
declare(strict_types=1);

namespace App\Service;

use Ayyobro\DoctrineToMermaid\MermaidDiagramGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use RuntimeException;

class MermaidDiagramS3Uploader
{
    /**
     * @param string $bucketName The S3 bucket name where diagrams will be stored.
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly S3Client $s3Client,
        private readonly string $bucketName
    ) {
    }

    /**
     * Generates a Mermaid diagram from your Doctrine entities and uploads it to S3.
     *
     * @param string $s3Key The key (path/filename) to use for the uploaded file in S3.
     *
     * @return string The URL of the uploaded file.
     *
     * @throws RuntimeException if the upload fails.
     */
    public function uploadDiagram(string $s3Key): string
    {
        // Retrieve all entity metadata and extract fully-qualified entity class names.
        $allMetadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $entityClasses = array_map(
            static fn($metadata) => $metadata->getName(),
            $allMetadata
        );

        // Generate the Mermaid diagram text.
        $generator = new MermaidDiagramGenerator($this->entityManager);
        $mermaidText = $generator->generate($entityClasses);

        try {
            // Upload the generated Mermaid diagram text to S3.
            $result = $this->s3Client->putObject([
                'Bucket'      => $this->bucketName,
                'Key'         => $s3Key,
                'Body'        => $mermaidText,
                'ContentType' => 'text/plain',
            ]);

            // Return the object URL from the S3 result.
            return (string) ($result['ObjectURL'] ?? '');
        } catch (AwsException $e) {
            throw new RuntimeException('Error uploading diagram to S3: ' . $e->getMessage());
        }
    }
}
```

## Example Output
Assuming you have two entities, `User` and `Post`, with the following simplified structures:
1. User:
    - id (PK)
    - username
    - email
    - posts (OneToMany relationship with Post)
2. Post:
    - id (PK)
    - title
    - content
    - author (ManyToOne relationship with User)

The generated Mermaid diagram might look like this:

```mermaid
erDiagram
    User {
        int id (PK)
        string username
        string email
    }

    Post {
        int id (PK)
        string title
        string content
    }

    User ||--o{ Post : "posts"
    Post }o--|| User : "author"
```
You can paste the output into a (Mermaid Live Editor)[https://mermaid.live] to visualize your entity relationships.

## Contributing
Contributions are welcome! Please fork this repository and submit a pull request with any improvements or bug fixes.

## License
This project is licensed under the terms of the [GNU General Public License v3.0](https://www.gnu.org/licenses/gpl-3.0.html).