-- Fix: add missing ENUM values and rename blank/mislogged document records
-- Root cause: type_document is an ENUM column and 'rapport_individuel' (plus others)
-- were never added to it, so those values were silently truncated to ''.
-- Run Step 1 first, then Step 2.

-- Step 1: Expand the ENUM to include all valid document types
ALTER TABLE documents_generes
MODIFY COLUMN type_document ENUM(
  'certificat_scolarite',
  'billet_excuse',
  'etat_mensualites',
  'releve_notes',
  'bulletin',
  'attestation_reussite',
  'convention_stage',
  'fiche_inscription',
  'recu_paiement',
  'fiche_preinscription',
  'liste_stagiaires',
  'etat_paiement',
  'rapport_individuel',
  'autre'
) NOT NULL DEFAULT 'autre';

-- Step 2: Fix the blank records (truncated because 'rapport_individuel' was not in ENUM)
UPDATE documents_generes
SET type_document = 'rapport_individuel'
WHERE TRIM(COALESCE(type_document, '')) = '';
