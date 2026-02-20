# Guia de Configuração de GPU AMD (ROCm) no Docker (Ubuntu)

Este guia ajuda a configurar os drivers AMD e permissões para permitir que o Docker use sua GPU via ROCm para acelerar modelos de IA (Ollama).

## 1. Verificar se a GPU foi detectada

Abra o terminal e rode:

```bash
ls -l /dev/dri/render*
ls -l /dev/kfd
```

Se esses arquivos existirem, o kernel do Linux já reconheceu sua GPU AMD. O Ollama precisa de ambos para funcionar.

## 2. Instalar Drivers e Pacotes ROCm

No Ubuntu moderno (22.04+), os drivers open source `amdgpu` já vêm no kernel. No entanto, para ter certeza que todas as bibliotecas de computação estão presentes, você pode instalar o pacote `rocm-hip-sdk` (opcional, mas recomendado se tiver problemas).

Para a maioria dos casos com Ollama, apenas garantir que o usuário tenha permissão de acesso aos dispositivos de vídeo é suficiente.

## 3. Configurar Permissões de Usuário

O Docker roda como um usuário específico (ou root dentro do container), mas precisa acessar o hardware do host. Para isso, adicione o seu usuário e o usuário do docker aos grupos de vídeo e renderização.

Rode no terminal:

```bash
sudo usermod -aG render,video $USER
```

**IMPORTANTE**: Você precisa fazer **logout e login** (ou reiniciar) para que essas alterações de grupo tenham efeito.

## 4. Configurar o Docker (Já feito no docker-compose)

No seu `docker-compose.yml`, já configuramos o mapeamento dos dispositivos:

```yaml
services:
  ollama:
    # ...
    devices:
      - "/dev/kfd"
      - "/dev/dri"
    # ...
```

Isso expõe a placa de vídeo direta para o container. A imagem oficial do Ollama já vem com suporte a ROCm embutido.

## 5. Rodar o Projeto

Após reiniciar (para aplicar as permissões de grupo), suba o ambiente:

```bash
docker compose up -d --build
```
