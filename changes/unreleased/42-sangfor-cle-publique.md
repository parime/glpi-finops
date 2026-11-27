### Fixed

- **Connexion à Sangfor SCP : lecture de la clé publique** (issue #42) : la réponse de
  `/janus/v2/public-key` et de `/janus/public-key` est acceptée sous ses deux formes documentées,
  sans enveloppe (`{"public_key": "..."}`, exemples de la documentation OPEN-API 6.10.0) ou dans
  l'enveloppe commune `{code, message, data}`. L'ancienne adresse (SCP 6.3.0 à 6.7) est désormais
  essayée dès que la v2 ne donne pas de clé utilisable (code 400, 404 ou 5xx, réponse sans clé), et
  plus seulement sur 404 ; elle ne l'est pas sur 401, 403 ou 429, les deux adresses partageant la
  même limite d'appels.
