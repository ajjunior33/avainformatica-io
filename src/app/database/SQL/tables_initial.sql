CREATE TABLE `whitecode`.`customer`(
	`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `telephone` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(255) NOT NULL,
    `document` VARCHAR(25) NOT NULL UNIQUE,
    `document_type` ENUM('cpf','cnpj') DEFAULT 'cpf',
    `create_at` DATETIME DEFAULT current_timestamp()
);

CREATE TABLE `whitecode`.`address`(
	`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `street` VARCHAR(255) NOT NULL,
    `number` VARCHAR(10) NOT NULL,
    `neighborhood` VARCHAR(255) NOT NULL,
    `city`VARCHAR(255) NOT NULL DEFAULT 'Cariacica',
    `state`VARCHAR(255) NOT NULL DEFAULT 'Espirito Santo',
    `country` VARCHAR(255) NOT NULL DEFAULT 'Brasil',
    `customer_id` INT NOT NULL,
    `create_at` DATETIME DEFAULT current_timestamp(),
    FOREIGN KEY (`customer_id`) REFERENCES `customer`(`id`)
);

CREATE TABLE `whitecode`.`computer`(
	`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `customer_id` INT NOT NULL,
    `key` VARCHAR(255) NOT NULL,
    `model` VARCHAR(255) NOT NULL,
    `marking` VARCHAR(255) NOT NULL,
    `serial_number` VARCHAR(255) NOT NULL,
    `type` ENUM('PC', 'SMARTPHONE', 'NOTEBOOK', 'TABLE', 'ALL IN ONE') DEFAULT 'PC',
    `delete` ENUM('0','1') DEFAULT '0',
    `create_at` DATETIME DEFAULT current_timestamp(),
    FOREIGN KEY (`customer_id`) REFERENCES `customer`(`id`)
);

CREATE TABLE `whitecode`.`parts`(
	`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `computer_id`INT NOT NULL,
    `customer_id` INT NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `amount` INT NOT NULL,
    `description` TEXT NOT NULL , 
    `create_at` DATETIME DEFAULT current_timestamp(),
    FOREIGN KEY (`computer_id`) REFERENCES `computer`(`id`),
    FOREIGN KEY (`customer_id`) REFERENCES `customer`(`id`)
);

CREATE TABLE `whitecode`.`support`(
	`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(255) NOT NULL UNIQUE,
    `computer_id`INT NOT NULL,
    `customer_id` INT NOT NULL,
    `description` TEXT NOT NULL,
    `status` ENUM('Em processo', 'Aguardando Resposta', 'Finalizado', 'Aguardando Peça', 'Em Curso') DEFAULT 'Em Processo',
    `create_at` DATETIME DEFAULT current_timestamp(),
    FOREIGN KEY (`computer_id`) REFERENCES `computer`(`id`),
    FOREIGN KEY (`customer_id`) REFERENCES `customer`(`id`)
);

CREATE TABLE `whitecode`.`attendance`(
	`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `protocol` VARCHAR(255) NOT NULL UNIQUE,
    `customer_id` INT NOT NULL,
    `description` TEXT NOT NULL,
    `create_at` DATETIME DEFAULT CURRENT_TIMESTAMP(),
    FOREIGN KEY (`customer_id`) REFERENCES `customer`(`id`)
);
    