-- Fix: assign correct type 'rapport_individuel' to all mislogged document records
-- Root cause: print_rapport_individuel.php called log_document_gen() with type 'rapport_individuel'
-- which was not in the allowed list, so it silently fell back to 'autre'.
-- Some records may also have been saved with an empty string due to older code.
-- Run this once on your database to fix all affected rows.

UPDATE documents_generes
SET type_document = 'rapport_individuel'
WHERE type_document = 'autre' OR type_document = '' OR type_document IS NULL;
