<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Création des tables `orders` et `order_item` (service Commande).
 *
 * Fournie pour démarrer immédiatement sur MariaDB (config officielle du TP / Docker).
 * En local sur SQLite, on utilise plutôt `doctrine:schema:create`.
 * Régénérable avec `php bin/console make:migration` après modification des entités.
 */
final class Version20260622000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Création des tables orders et order_item (référence, statut, items, montants en centimes, adresse de livraison, idempotency_key).';
    }

    public function up(Schema $schema): void
    {
        // SQL MariaDB / MySQL
        $this->addSql(<<<'SQL'
            CREATE TABLE orders (
                id INT AUTO_INCREMENT NOT NULL,
                reference VARCHAR(32) NOT NULL,
                customer_id VARCHAR(64) NOT NULL,
                cart_id VARCHAR(64) DEFAULT NULL,
                customer_email VARCHAR(180) DEFAULT NULL,
                status VARCHAR(255) NOT NULL,
                total_amount_cents INT NOT NULL,
                currency VARCHAR(3) NOT NULL,
                idempotency_key VARCHAR(64) DEFAULT NULL,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                shipping_address_street VARCHAR(255) NOT NULL,
                shipping_address_city VARCHAR(120) NOT NULL,
                shipping_address_postal_code VARCHAR(20) NOT NULL,
                shipping_address_country VARCHAR(80) NOT NULL,
                UNIQUE INDEX UNIQ_E52FFDEEAEA34913 (reference),
                UNIQUE INDEX UNIQ_E52FFDEE7FD1C147 (idempotency_key),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE order_item (
                id INT AUTO_INCREMENT NOT NULL,
                order_id INT NOT NULL,
                product_id VARCHAR(64) NOT NULL,
                product_name VARCHAR(255) NOT NULL,
                quantity INT NOT NULL,
                unit_price_cents INT NOT NULL,
                INDEX IDX_52EA1F098D9F6D38 (order_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql('ALTER TABLE order_item ADD CONSTRAINT FK_52EA1F098D9F6D38 FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE order_item DROP FOREIGN KEY FK_52EA1F098D9F6D38');
        $this->addSql('DROP TABLE order_item');
        $this->addSql('DROP TABLE orders');
    }
}
