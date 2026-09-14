-- Purge des comptes de démonstration créés par `php bin/console app:demo:seed`
-- (backend/src/Command/SeedDemoDataCommand.php).
--
-- À quoi ça sert : la commande de seed refuse de s'exécuter si l'un des trois
-- comptes existe déjà, et ne supprime jamais rien. Pour rejouer une démo
-- depuis un état vierge (après une répétition qui a créé des entrées, des
-- messages ou accepté l'invitation soignant), il faut donc purger d'abord,
-- puis relancer le seed.
--
-- Portée : STRICTEMENT les trois comptes listés ci-dessous et les données qui
-- leur sont rattachées. La liste d'emails est fermée et écrite en clair —
-- volontairement, plutôt qu'un LIKE '%-test@%' qui pourrait emporter le compte
-- d'un utilisateur réel dont l'adresse contiendrait « -test ».
--
-- Tout est dans une transaction unique : en cas d'erreur sur une table, rien
-- n'est supprimé.
--
-- Usage en local :
--   docker compose exec -T db psql -U medlink -d medlink -v ON_ERROR_STOP=1 \
--     < deploy/demo-reset.sql
--   docker compose exec app php bin/console app:demo:seed
--
-- Usage en production (à faire en connaissance de cause) :
--   docker compose -f docker-compose.prod.yml exec -T db \
--     psql -U medlink -d medlink -v ON_ERROR_STOP=1 < deploy/demo-reset.sql
--   docker compose -f docker-compose.prod.yml exec app php bin/console app:demo:seed

BEGIN;

CREATE TEMPORARY TABLE demo_user ON COMMIT DROP AS
SELECT id
FROM "user"
WHERE email IN (
    'patient-test@medlink-app.fr',
    'aidant-test@medlink-app.fr',
    'soignant-test@medlink-app.fr'
);

-- L'ordre suit les clés étrangères, des feuilles vers "user".

DELETE FROM appointment
WHERE patient_id IN (SELECT id FROM demo_user)
   OR soignant_id IN (SELECT id FROM demo_user);

DELETE FROM treatment_intake
WHERE treatment_schedule_id IN (
    SELECT s.id
    FROM treatment_schedule s
    JOIN treatment t ON t.id = s.treatment_id
    WHERE t.patient_id IN (SELECT id FROM demo_user)
       OR t.prescribed_by_id IN (SELECT id FROM demo_user)
);

DELETE FROM treatment_schedule
WHERE treatment_id IN (
    SELECT id FROM treatment
    WHERE patient_id IN (SELECT id FROM demo_user)
       OR prescribed_by_id IN (SELECT id FROM demo_user)
);

DELETE FROM treatment
WHERE patient_id IN (SELECT id FROM demo_user)
   OR prescribed_by_id IN (SELECT id FROM demo_user);

DELETE FROM journal_entry_comment
WHERE author_id IN (SELECT id FROM demo_user)
   OR journal_entry_id IN (
        SELECT id FROM journal_entry
        WHERE patient_id IN (SELECT id FROM demo_user)
           OR author_id IN (SELECT id FROM demo_user)
   );

DELETE FROM journal_entry
WHERE patient_id IN (SELECT id FROM demo_user)
   OR author_id IN (SELECT id FROM demo_user);

DELETE FROM message
WHERE sender_id IN (SELECT id FROM demo_user)
   OR recipient_id IN (SELECT id FROM demo_user);

-- Couvre aussi la liaison patient↔soignant créée pendant la démo par le flux
-- d'invitation : le seed n'en crée aucune, mais une répétition en laisse une.
DELETE FROM patient_aidant
WHERE patient_id IN (SELECT id FROM demo_user)
   OR aidant_id IN (SELECT id FROM demo_user);

DELETE FROM patient_soignant
WHERE patient_id IN (SELECT id FROM demo_user)
   OR soignant_id IN (SELECT id FROM demo_user);

DELETE FROM password_reset_token
WHERE user_id IN (SELECT id FROM demo_user);

-- refresh_tokens référence l'utilisateur par son identifiant de connexion
-- (l'email), pas par une clé étrangère.
DELETE FROM refresh_tokens
WHERE username IN (
    'patient-test@medlink-app.fr',
    'aidant-test@medlink-app.fr',
    'soignant-test@medlink-app.fr'
);

DELETE FROM "user"
WHERE id IN (SELECT id FROM demo_user);

COMMIT;
