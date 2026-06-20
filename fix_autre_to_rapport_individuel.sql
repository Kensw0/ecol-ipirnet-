-- Fix: rename all 'autre' document history records to their correct type 'rapport_individuel'
-- Root cause: print_rapport_individuel.php called log_document_gen() with type 'rapport_individuel'
-- which was not in the allowed list, so it silently fell back to 'autre'.
-- This updates all existing affected rows in the database.

UPDATE documents_generes
SET type_document = 'rapport_individuel'
WHERE type_document = 'autre';
