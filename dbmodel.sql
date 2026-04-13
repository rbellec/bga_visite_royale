CREATE TABLE IF NOT EXISTS `vr_pieces` (
  `piece_id` INT(2) NOT NULL,
  `piece_type` VARCHAR(10) NOT NULL,
  `position` INT(2) NOT NULL,
  PRIMARY KEY (`piece_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `vr_card` (
  `card_id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `card_type` VARCHAR(20) NOT NULL,
  `card_type_arg` INT(11) NOT NULL,
  `card_location` VARCHAR(20) NOT NULL,
  `card_location_arg` INT(11) NOT NULL,
  PRIMARY KEY (`card_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1;
