# SPED-POS

Biblioteca PHP para **impressão do DANFC-e (NFC-e)** em **impressoras térmicas compatíveis com ESC/POS**, a partir do XML da NFC-e.

Compatível com impressoras térmicas via **rede, USB, serial, CUPS ou outros conectores**, utilizando ESC/POS em PHP.

---

![Build Status](https://img.shields.io/travis/alves/sped-pos/master.svg?style=flat-square)
![Coverage Status](https://img.shields.io/scrutinizer/coverage/g/alves/sped-pos.svg?style=flat-square)
![Quality Score](https://img.shields.io/scrutinizer/g/alves/sped-pos.svg?style=flat-square)

![Latest Stable Version](https://poser.pugx.org/alves/sped-pos/version)
![Latest Version on Packagist](https://img.shields.io/packagist/v/alves/sped-pos.svg?style=flat-square)
![License](https://poser.pugx.org/alves/nfephp/license.svg?style=flat-square)
![Total Downloads](https://img.shields.io/packagist/dt/alves/sped-pos.svg?style=flat-square)

![Issues](https://img.shields.io/github/issues/alves/sped-pos.svg?style=flat-square)
![Forks](https://img.shields.io/github/forks/alves/sped-pos.svg?style=flat-square)
![Stars](https://img.shields.io/github/stars/alves/sped-pos.svg?style=flat-square)

---

## ✨ Funcionalidades

- Impressão completa do **DANFC-e**
- Suporte a **logo**
- Impressoras térmicas **ESC/POS**
- Suporte a múltiplos **tipos de conexão**
- Leitura direta do **XML da NFC-e**
- Integração com `escpos-php`

---

## 📦 Requisitos

- PHP 7.0 ou superior
- Composer
- Impressora térmica compatível com ESC/POS

---

## 🚀 Instalação

```bash
composer require alves/sped-pos
composer require alves/escpos-php
