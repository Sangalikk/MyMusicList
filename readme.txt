Para conseguir abrir o site
1. Instale o Composer
2. Use o comando -> composer install 
3. Instale o XAMPP 
4. Habilite o Apache e Mysql
5. Vá no VS Code e abra o terminal usando CTRL "/'
6. Digite -> mysql -u root 
6.1 Se não funcionar, abra o apache e vá em Apache -> Config -> phpMyAdmin -> $cfg['Servers'][$i]['AllowNoPassword'] = false; (mude false para true)
6.2 Se ainda não funcionar vá em Editar Variáveis de Ambiente para Minha Conta, selecione o Path vá em editar, depois procurar e procure a pasta bin que está dentro da pasta Mysql na pasta do XAMPP 
6.3 Reinicie o VS Code
7. No terminal digite o comando -> create database MyMusicList; 
8. Ainda no terminal digite o comando -> use MyMusicList; 
9. Verifique se a pasta do projeto está dentro da pasta htdocs do XAMPP 
10. Abra o navegador e digite -> localhost/MyMusicList/paginaRegistrar.php
Agora é só fazer o cadastro e testar o projeto 
