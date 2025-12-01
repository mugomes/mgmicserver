# MGMicServer

Com MGMicServer você pode usar o microfone do seu computador principal (servidor) e enviar 
o áudio sem ruído e chiados para o seu computador secundário (cliente).

## Download

Baixe a versão mais recente disponível nos 
[releases](https://github.com/mugomes/mgmicserver/releases)

**Requerimento**

- Linux Ubuntu 24.04 ou superior (64 bits)
- avahi-utils
- pulseaudio
- pulseaudio-utils

## Documentação

No seu computador que contém o microfone baixe a versão MGMicServer e no 
computador que irá receber o som do servidor baixe a versão MGMicServer-Client.

Abra o terminal e aplique permissão de execução para os dois executáveis, 
tanto no servidor quanto no cliente.

```bash
chmod +x mgmicserver
chmod +x mgmicserver-client
```

No servidor execute: `./mgmicserver`
No cliente execute: `./mgmicserver-client`

Caso seja a primeira vez que está executando o mgmicserver-client irá gerar um 
arquivo config.json, preencha o hostname com o nome da máquina do seu servidor, 
exemplo "seu-pc". Não use ".local" no final, pois isso já foi configurado.

Você também pode alterar a porta no arquivo config.json ou deixar a porta que 
está como padrão, caso altere, lembre-se de alterar também no lado do servidor.

## Support

- GitHub: https://github.com/sponsors/mugomes/
- More: https://mugomes.github.io/apoie.html

## License

The MGMicServer is provided under:

[SPDX-License-Identifier: 
GPL-2.0-only](https://github.com/mugomes/mgmicserver/blob/main/LICENSE)

Beign under the terms of the GNU General Public License version 2 only.

All contributions to the MGMicServer are subject to this license.
