-- SQL Script um die Foreign Key Constraints zu korrigieren
-- Die Constraints verweisen noch auf die alte jungschuetzen Tabelle,
-- müssen aber auf endstich_gaeste verweisen

-- Zuerst die alten Constraints entfernen
ALTER TABLE `endstich_jung` DROP FOREIGN KEY `endstich_jung_ibfk_1`;
ALTER TABLE `schwini_jung` DROP FOREIGN KEY `schwini_jung_ibfk_1`;
ALTER TABLE `zabig_jung` DROP FOREIGN KEY `zabig_jung_ibfk_1`;

-- Neue Constraints hinzufügen, die auf endstich_gaeste verweisen
ALTER TABLE `endstich_jung` 
ADD CONSTRAINT `endstich_jung_gaeste_fk` 
FOREIGN KEY (`JungschuetzeID`) REFERENCES `endstich_gaeste` (`id`) ON DELETE CASCADE;

ALTER TABLE `schwini_jung` 
ADD CONSTRAINT `schwini_jung_gaeste_fk` 
FOREIGN KEY (`JungschuetzeID`) REFERENCES `endstich_gaeste` (`id`) ON DELETE CASCADE;

ALTER TABLE `zabig_jung` 
ADD CONSTRAINT `zabig_jung_gaeste_fk` 
FOREIGN KEY (`JungschuetzeID`) REFERENCES `endstich_gaeste` (`id`) ON DELETE CASCADE;
