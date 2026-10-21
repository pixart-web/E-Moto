# E-Motogreen Tablet (Android)

Aplicação Android para tablet com dois acessos no arranque:

- **Plataforma**: CRM completo alojado em `https://e-moto.motogreen.pt/`.
- **Agenda Live**: agenda da Oficina, só de leitura e atualizada a cada 15 segundos.

O seletor PT/IT traduz a interface da app e a Agenda Live. A Plataforma mantém o idioma atualmente implementado no CRM.

## Gerar o APK

1. Abrir a pasta `android-app` no Android Studio (JDK 17).
2. Aguardar o Gradle Sync. O projeto usa Android Gradle Plugin 9.4.0, compatível com Gradle 9.6.0.
3. Instalar o Android SDK 35 quando solicitado.
4. Selecionar **Build > Generate App Bundles or APKs > Generate APKs**.
5. O APK de testes será criado em `app/build/outputs/apk/debug/app-debug.apk`.

Para distribuição interna definitiva, usar **Generate Signed App Bundle / APK** e guardar a chave de assinatura num local seguro. A aplicação exige Android 8.0 ou superior e ligação HTTPS.
