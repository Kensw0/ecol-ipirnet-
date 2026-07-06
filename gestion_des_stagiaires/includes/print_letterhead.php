<?php
/**
 * print_letterhead.php — En-tête officiel IPIRNET commun à tous les documents imprimés.
 *
 * À inclure avec require après avoir défini les constantes suivantes dans le fichier hôte :
 *   string $SCHOOL_ORG          — ex : 'GROUPE IPIRNET'
 *   string $SCHOOL_TAGLINE_1    — première ligne de l'intitulé
 *   string $SCHOOL_TAGLINE_2    — deuxième ligne (chaîne vide pour l'omettre)
 *   string $SCHOOL_AUTH_LINE_1  — autorisation ministérielle
 *   string $SCHOOL_AUTH_LINE_2  — accréditation
 *
 * Le fichier hôte doit inclure les règles CSS du bloc en-tête (.cs-head, .cs-head-left,
 * .cs-head-right, .cs-head-mid, .cs-head-logo, .cs-org, .cs-tag, .cs-auth).
 */
?>
<table class="cs-head">
    <tr>
        <td class="cs-head-left">
            <img src="assets/img/logo.png"
                 alt="Logo <?= h($SCHOOL_ORG) ?>"
                 class="cs-head-logo">
        </td>
        <td class="cs-head-mid">
            <div class="cs-org"><?= h($SCHOOL_ORG) ?></div>
            <div class="cs-tag"><?= h($SCHOOL_TAGLINE_1) ?></div>
            <?php if (!empty($SCHOOL_TAGLINE_2)): ?>
            <div class="cs-tag"><?= h($SCHOOL_TAGLINE_2) ?></div>
            <?php endif; ?>
            <div class="cs-auth"><?= h($SCHOOL_AUTH_LINE_1) ?></div>
            <div class="cs-auth"><?= h($SCHOOL_AUTH_LINE_2) ?></div>
        </td>
        <td class="cs-head-right">
            <img src="assets/img/stamp_accredite.jpg"
                 alt="Accrédité par l'État"
                 style="width:80px;height:80px;object-fit:contain;border-radius:50%;">
        </td>
    </tr>
</table>
