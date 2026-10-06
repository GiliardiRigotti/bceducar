# Endereços e pontos das escolas cadastradas — 05/10/2026

Organizadas as 15 unidades existentes no banco local. Este cadastro é uma amostra da rede municipal, não a relação completa das escolas de Balneário Camboriú.

Antes da correção, todas estavam sem endereço principal e com latitude -26.99 / longitude -48.63, sobrepondo os marcadores. Agora cada unidade tem endereço principal em places/person_has_place (tipo 1), cidade IBGE 4202008 e um ponto de referência distinto em pmieducar.escola e no endereço. A view public.schools da pré-matrícula lê diretamente as coordenadas nativas.

Endereços confrontados com diretórios e notícias da Prefeitura. Coordenadas do BuscaEscola (referência Educacenso 2025) conferidas por consulta reversa ao OpenStreetMap/Nominatim: todas retornaram Balneário Camboriú e bairro compatível. Isso confirma região/via próxima, não certifica o portão de entrada. Ivo Silveira e Carrossel usam os pontos dos edifícios escolares no OpenStreetMap. O ponto anterior do diretório para Carrossel retornava Rua Holanda e foi substituído pelo edifício da Rua Grécia, 205.

| Unidade | Endereço registrado | Bairro | Latitude | Longitude |
|---|---|---|---|---|
| CEM Presidente Médici | Rua Paraguai, 1005 | Nações | -26.9766096 | -48.6469257 |
| CEM Governador Ivo Silveira | Avenida Santa Catarina, 637 | Estados | -26.9955766 | -48.6512082 |
| CEM Tomaz Francisco Garcia | Rua Biguaçu, 841 | Municípios | -27.0078656 | -48.6422978 |
| CEM CAIC | Rua Angelina, s/n | Municípios | -27.0071309 | -48.639887 |
| CEM Ariribá | Avenida dos Tucanos, 60 | Ariribá | -26.9728647 | -48.6436625 |
| CEM Dona Lili | Rua Fermino Taveira Cruz, 219 | Barra | -27.0127946 | -48.6085285 |
| CEM Nova Esperança | Rua José Honorato da Silva, s/n | Nova Esperança | -27.0272008 | -48.6114944 |
| CEM Taquaras | Avenida Rodesindo Pavan, 1048 | Taquaras | -27.01053 | -48.58006 |
| NEI Bom Sucesso | Rua Maria Joaquina Corrêa, 307 | Barra | -27.0090497 | -48.5976232 |
| NEI Cristo Luz | Rua Apiúna, 137 | Vila Real | -27.0049421 | -48.6323565 |
| NEI Pioneiros | Rua Alípio Evilásio Meirinho, número não publicado | Pioneiros | -26.96829 | -48.63595 |
| NEI Carrossel | Rua Grécia, 205 | Nações | -26.9860739 | -48.6419913 |
| NEI Sonho de Criança | Rua Itália, 1001 | Nações | -26.98108 | -48.64942 |
| NEI Taquaras | Avenida Rodesindo Pavan, s/n | Taquaras | -27.0119465 | -48.58084836 |
| NEI Sementes do Amanhã | Rua Angelina, 595 | Municípios | -27.00668 | -48.63892 |

## Divergências e precisão

- NEI Pioneiros: endereço da nova sede na Rua Alípio Evilásio Meirinho confirmado pela Prefeitura (inauguração em julho de 2025). Substituído o endereço antigo da Rua Miguel Matte, 586. Número/CEP da nova sede não foram inferidos. Coordenada pública de referência perto da Rua Egídio Alfredo Crispim; confirmação do portão da nova sede pendente, registrada em coordinate_status/notes.
- CEM Tomaz Francisco Garcia: número 841 corroborado por inventário municipal e Educacenso 2025; o diretório geral informa 481. Adotado 841.
- NEI Cristo Luz: Rua Apiúna, 137, corroborada por notícia municipal, Educacenso 2025 e consulta geográfica. Diretório geral diverge (5ª Avenida, 137).
- Pontos são referências escolares, não medição topográfica ou certificação da entrada. Nenhum marcador foi deslocado artificialmente para distribuir visualmente as escolas.

## Aplicação e validação

Catálogo versionado: resources/data/bc-school-locations.json. Comando bc:sync-school-locations exibe prévia; --apply grava as 15 unidades identificadas por cadastro e nome numa transação, após backup dos dados anteriores. Endereços principais compartilhados ou duplicados bloqueiam a escrita. Nenhuma unidade ou turma nova foi criada.

Aplicado no banco local em 05/10/2026. Backup: storage/app/school-location-backups/20261005-211851-11c6d0ca-c075-43c9-a0f0-46726e7bc496.json. Novas criações pelo seeder BC usam o catálogo revisado.

Mapas de consulta de escolas e edição interna enquadram automaticamente os marcadores disponíveis. Seleção pública já inclui todas as unidades elegíveis e enquadra seus pontos. Filtros de série, turno, processo e vagas preservados.

Validação: 2 testes PHP/111 asserções (endereços nativos, consistência com PMD e idempotência), 24 testes frontend aprovados (incluindo seleção e limites do mapa), build Vite concluído e publicado. Verificação direta: scripts/verify-bc-school-locations.php. Não houve inspeção visual no navegador nesta execução.

## Fontes por unidade

- **CEM Presidente Médici**: [endereço](https://antigo.bc.sc.gov.br/conteudo.cfm?caminho=centros-educacionais-municipais); [coordenada](https://buscaescola.com.br/escolas/sc/balneario-camboriu/42070090-cem-presidente-medici.html).
- **CEM Governador Ivo Silveira**: [endereço](https://antigo.bc.sc.gov.br/conteudo.cfm?caminho=centros-educacionais-municipais); [coordenada](https://www.openstreetmap.org/way/702968525).
- **CEM Tomaz Francisco Garcia**: [endereço](https://www.bc.sc.gov.br/arquivos/licitacao/WP5UQ5UF.pdf); [coordenada](https://buscaescola.com.br/escolas/sc/balneario-camboriu/42069955-c-e-m-tomaz-francisco-garcia.html).
- **CEM CAIC**: [endereço](https://antigo.bc.sc.gov.br/conteudo.cfm?caminho=centros-educacionais-municipais); [coordenada](https://buscaescola.com.br/escolas/sc/balneario-camboriu/42124530-caic-ayrton-senna-da-silva.html).
- **CEM Ariribá**: [endereço](https://antigo.bc.sc.gov.br/conteudo.cfm?caminho=centros-educacionais-municipais); [coordenada](https://buscaescola.com.br/escolas/sc/balneario-camboriu/42124514-cem-aririba.html).
- **CEM Dona Lili**: [endereço](https://antigo.bc.sc.gov.br/conteudo.cfm?caminho=centros-educacionais-municipais); [coordenada](https://buscaescola.com.br/escolas/sc/balneario-camboriu/42139228-c-e-m-dona-lili.html).
- **CEM Nova Esperança**: [endereço](https://antigo.bc.sc.gov.br/conteudo.cfm?caminho=centros-educacionais-municipais); [coordenada](https://buscaescola.com.br/escolas/sc/balneario-camboriu/42069947-cem-nova-esperanca.html).
- **CEM Taquaras**: [endereço](https://antigo.bc.sc.gov.br/conteudo.cfm?caminho=centros-educacionais-municipais); [coordenada](https://buscaescola.com.br/escolas/sc/balneario-camboriu/42070023-c-e-m-taquaras.html).
- **NEI Bom Sucesso**: [endereço](https://antigo.bc.sc.gov.br/conteudo.cfm?caminho=nucleos-de-educacao-infantil); [coordenada](https://buscaescola.com.br/escolas/sc/balneario-camboriu/42069823-nei-bom-sucesso.html).
- **NEI Cristo Luz**: [endereço](https://balneariocamboriu.sc.gov.br/imprensa_detalhe.cfm?codigo=20189); [coordenada](https://buscaescola.com.br/escolas/sc/balneario-camboriu/42069831-n-e-i-cristo-luz.html).
- **NEI Pioneiros**: [endereço](https://www.balneariocamboriu.sc.gov.br/imprensa_detalhe.cfm?codigo=39206); [coordenada](https://buscaescola.com.br/escolas/sc/balneario-camboriu/42142830-n-e-i-pioneiros.html).
- **NEI Carrossel**: [endereço](https://antigo.bc.sc.gov.br/conteudo.cfm?caminho=nucleos-de-educacao-infantil); [coordenada](https://www.openstreetmap.org/way/914878940).
- **NEI Sonho de Criança**: [endereço](https://antigo.bc.sc.gov.br/conteudo.cfm?caminho=nucleos-de-educacao-infantil); [coordenada](https://buscaescola.com.br/escolas/sc/balneario-camboriu/42069840-n-e-i-sonho-de-crianca.html).
- **NEI Taquaras**: [endereço](https://antigo.bc.sc.gov.br/conteudo.cfm?caminho=nucleos-de-educacao-infantil); [coordenada](https://buscaescola.com.br/escolas/sc/balneario-camboriu/42103100-n-e-i-taquaras.html).
- **NEI Sementes do Amanhã**: [endereço](https://antigo.bc.sc.gov.br/conteudo.cfm?caminho=nucleos-de-educacao-infantil); [coordenada](https://buscaescola.com.br/escolas/sc/balneario-camboriu/42146593-n-e-i-sementes-do-amanha.html).

Geografia de apoio: [OpenStreetMap, licença ODbL](https://www.openstreetmap.org/copyright) e [Nominatim](https://nominatim.openstreetmap.org/).
