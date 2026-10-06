# Adaptações BC do PMD

Revisão base: `b73af875578ffbbb7ac1157e1aff64bca518d45f`.

O patch contém as alterações dos arquivos rastreados do pacote. `added/` contém os novos componentes, recursos do mapa e testes; `manifest.json` fixa a revisão e os checksums. Não inclui `.env`, credenciais, banco ou documentos de responsáveis.

O instalador `scripts/install-pmd.ps1` aplica o conjunto antes do Composer e do build. Aplicação manual, com Python 3 e Git:

```powershell
python scripts/apply-pmd-customizations.py
```

O script aceita um checkout limpo ou o conjunto já aplicado. Alteração parcial, revisão diferente ou arquivo adicional conflitante interrompe a instalação antes de sobrescrever os arquivos. A recompilação do frontend continua necessária em uma instalação nova.

Após alterar o pacote, atualizar o conjunto com:

```powershell
python scripts/export-pmd-customizations.py
```

A reconstrução foi verificada em um checkout local limpo da revisão fixa, sem rede; reaplicar o conjunto não alterou o resultado. Os arquivos BC do projeto principal e esta pasta devem acompanhar a mesma entrega/versionamento. Nenhum commit/publicação é executado pelos scripts.
