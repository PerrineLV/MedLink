import { Link } from 'react-router-dom';
import './PrivacyPolicyPage.css';

export default function PrivacyPolicyPage() {
  return (
    <main className="privacy-page">
      <div className="privacy-card">
        <h1>MedLink</h1>
        <p className="privacy-subtitle">Politique de confidentialité</p>

        <section>
          <h2>Données traitées</h2>
          <p>
            Dans le cadre de votre suivi médical à domicile, MedLink traite des données de santé
            vous concernant : entrées de votre journal de suivi (humeur, douleur, tension, notes
            libres), informations relatives à vos traitements, ainsi que les messages échangés avec
            les aidants et professionnels de santé qui vous accompagnent.
          </p>
        </section>

        <section>
          <h2>Finalité</h2>
          <p>
            Ces données sont utilisées exclusivement pour assurer la coordination de votre suivi
            médical entre vous, vos aidants et les professionnels de santé rattachés à votre
            dossier.
          </p>
        </section>

        <section>
          <h2>Base légale</h2>
          <p>
            Le traitement repose sur votre consentement explicite, recueilli lors de votre
            inscription (article 6.1.a du RGPD). S’agissant de données de santé, ce consentement
            explicite constitue également la base légale requise par l’article 9.2.a du RGPD pour le
            traitement de données dites « sensibles ».
          </p>
        </section>

        <section>
          <h2>Durée de conservation</h2>
          <p>
            Vos données sont conservées pendant toute la durée d’utilisation de votre compte. En cas
            de clôture du compte, elles sont supprimées dans un délai de 3 ans, sauf obligation
            légale de conservation plus longue.
          </p>
        </section>

        <section>
          <h2>Vos droits</h2>
          <p>
            Conformément au RGPD, vous disposez d’un droit d’accès, de rectification, de portabilité
            et d’effacement de vos données. Vous pouvez exercer ces droits directement depuis la
            rubrique <Link to="/account">Mon compte</Link> une fois connecté·e.
          </p>
        </section>

        <section>
          <h2>Hébergement</h2>
          <p>
            Vos données sont hébergées par Infomaniak, hébergeur certifié ISO 14001, dont les
            centres de données sont situés en Suisse.
          </p>
        </section>

        <section>
          <h2>Contact</h2>
          <p>
            Pour toute question relative à vos données personnelles, vous pouvez nous contacter à
            l’adresse suivante : <a href="mailto:dpo@medlink.test">dpo@medlink.test</a>.
          </p>
        </section>

        <p className="privacy-back-link">
          <Link to="/login">Retour à la connexion</Link>
        </p>
      </div>
    </main>
  );
}
