<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Création de la table `review` (étape 3).
 *
 * Fournie pour démarrer immédiatement. Vous pouvez la régénérer avec :
 *   php bin/console make:migration
 * après avoir (re)défini l'entité Review.
 */
final class Version20260612000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Création de la table review (rating, comment, product_id, author, created_at).';
    }

    public function up(Schema $schema): void
    {
        // SQL MariaDB / MySQL
        $this->addSql(<<<'SQL'
            CREATE TABLE review (
                id INT AUTO_INCREMENT NOT NULL,
                rating INT NOT NULL,
                comment LONGTEXT NOT NULL,
                product_id INT NOT NULL,
                author VARCHAR(255) NOT NULL,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE review');
    }
}
