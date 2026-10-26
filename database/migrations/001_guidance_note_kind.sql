-- Upgrade an existing database created before guidance note kinds existed.
ALTER TABLE guidance_notes ADD COLUMN `kind` ENUM('Note','Commendation','Parent consultation','Support case') NOT NULL DEFAULT 'Note' AFTER author_name;
