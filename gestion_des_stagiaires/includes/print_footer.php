<?php
/**
 * print_footer.php — Pied de page officiel IPIRNET commun à tous les documents imprimés.
 *
 * À inclure avec require après avoir défini les constantes suivantes dans le fichier hôte :
 *   string $SCHOOL_ADDRESS — adresse postale complète avec téléphone
 *   string $SCHOOL_LEGAL   — email, RC, patente, IF
 *
 * Le fichier hôte doit inclure la règle CSS .cs-footer.
 */
?>
<div class="cs-footer">
    <?= h($SCHOOL_ADDRESS) ?><br>
    <?= h($SCHOOL_LEGAL) ?>
</div>
