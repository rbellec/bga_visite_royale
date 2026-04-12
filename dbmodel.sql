CREATE TABLE IF NOT EXISTS `vr_pieces` (
  `piece_id` INT(2) NOT NULL,
  `piece_type` VARCHAR(10) NOT NULL,
  `position` INT(2) NOT NULL,
  PRIMARY KEY (`piece_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `vr_cards` (
  `card_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `card_type` VARCHAR(10) NOT NULL,
  `card_value` INT(1) NOT NULL,
  `card_subtype` VARCHAR(10) DEFAULT NULL,
  `card_location` VARCHAR(16) NOT NULL DEFAULT 'deck',
  `card_location_arg` INT NOT NULL DEFAULT 0,
  `card_order` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`card_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1;
