# Alignement sur le SDK Expo (ML-171)

Ce projet suit une politique volontairement stricte dans un seul sens : un
paquet géré par le SDK Expo (listé dans `node_modules/expo/bundledNativeModules.json`)
ne doit **jamais dépasser le mineur suivant** celui attendu par le SDK
installé. En dessous de cette borne, y compris un patch antérieur au dernier
publié par Expo, c'est accepté — c'est précisément ce qui laisse passer les
correctifs de sécurité proposés par Dependabot sans attendre qu'Expo
republie le paquet concerné.

Deux contrôles appliquent cette même règle à deux moments différents :

| Contrôle                                                            | Moment           | Rôle                                                                                                         |
| ------------------------------------------------------------------- | ---------------- | ------------------------------------------------------------------------------------------------------------ |
| `.github/dependabot.yml` (bloc `ignore` de `/frontend-mobile`)      | à la proposition | empêche Dependabot de proposer une PR qui dépasserait la borne                                               |
| `scripts/check-sdk-alignment.mjs`, lancé en CI bloquante (`ci.yml`) | à chaque PR      | attrape l'erreur humaine si une version désalignée arrive quand même (bump manuel, résolution transitive...) |

Le script CI lit `bundledNativeModules.json` **à l'exécution** : il n'a rien à
régénérer, il se recale tout seul à chaque changement de SDK. **Seules les
bornes de `dependabot.yml` sont statiques** et doivent être régénérées à la
main après toute montée de version du SDK Expo (`npx expo install expo@latest`
ou équivalent).

## Régénérer les bornes de dependabot.yml

Après une montée de SDK, une fois `npm install` rejoué et
`node_modules/expo/bundledNativeModules.json` à jour :

```bash
cd frontend-mobile
node -e "
const fs = require('fs');
const manifest = JSON.parse(fs.readFileSync('node_modules/expo/bundledNativeModules.json'));
const pkg = JSON.parse(fs.readFileSync('package.json'));
const expoVersion = JSON.parse(fs.readFileSync('node_modules/expo/package.json')).version;
const sdkMajor = expoVersion.split('.')[0];
const deps = new Set([...Object.keys(pkg.dependencies || {}), ...Object.keys(pkg.devDependencies || {})]);
for (const [name, pin] of Object.entries(manifest)) {
  if (!deps.has(name)) continue;
  const numeric = pin.replace(/^[~^]/, '');
  const [major, minor] = numeric.split('.').map(Number);
  console.log(\`      - dependency-name: \"\${name}\"\n        versions: [\">=\${major}.\${minor + 1}.0\"] # SDK \${sdkMajor} : \${pin}\`);
}
"
```

Remplacer intégralement le bloc d'entrées `dependency-name` sous le
commentaire ML-171 dans `.github/dependabot.yml` par la sortie de cette
commande (elle ne liste que les paquets du manifeste réellement présents dans
`package.json` — les entrées `expo` lui-même, `@react-navigation/*`, `axios`,
`react-native-keyboard-aware-scroll-view` n'y apparaîtront jamais, elles ne
sont pas pilotées par le SDK et continuent d'être suivies normalement par
Dependabot).

Ne pas corriger les bornes une par une à la main : une montée de SDK change
généralement la majorité des paquets listés, et une correction partielle
laisserait certaines bornes viser l'ancien SDK sans que rien ne le signale.

## Après régénération

1. Vérifier que `node scripts/check-sdk-alignment.mjs` passe (il doit — il
   lit le même manifeste, à l'exécution).
2. Committer `.github/dependabot.yml` et le `package.json`/`package-lock.json`
   de la montée de SDK dans la même PR : des bornes qui pointent vers un SDK
   pas encore installé sont aussi trompeuses que des bornes obsolètes.

## expo-doctor

`npx expo-doctor` (21 contrôles au total, dont l'alignement de version mais
aussi la cohérence `app.json`/`eas.json`, les permissions natives déclarées,
etc.) tourne chaque semaine dans `.github/workflows/expo-doctor.yml`,
**non bloquant**. Il est volontairement absent de la CI bloquante : il exige
la toute dernière version publiée pour chaque paquet du SDK, une règle plus
stricte et différente de celle ci-dessus — il peut donc être rouge en
hebdomadaire sans qu'aucun projet ne soit cassé, simplement parce qu'Expo a
publié un patch depuis la dernière vérification. Un rouge sur ce workflow est
une information (« des correctifs existent »), pas une alerte à traiter en
urgence.
