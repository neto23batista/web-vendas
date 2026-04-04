CREATE DATABASE IF NOT EXISTS farmavida CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE farmavida;


CREATE TABLE IF NOT EXISTS usuarios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    senha VARCHAR(255) NOT NULL,
    tipo ENUM('cliente', 'dono') DEFAULT 'cliente',
    telefone VARCHAR(20),
    endereco TEXT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;


CREATE TABLE IF NOT EXISTS produtos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(150) NOT NULL,
    descricao TEXT,
    preco DECIMAL(10,2) NOT NULL,
    categoria VARCHAR(60),
    imagem VARCHAR(255),
    disponivel TINYINT(1) DEFAULT 1,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_categoria (categoria),
    INDEX idx_disponivel (disponivel)
) ENGINE=InnoDB;


CREATE TABLE IF NOT EXISTS carrinho (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_cliente INT NOT NULL,
    id_produto INT NOT NULL,
    tipo_produto ENUM('normal', 'especial') DEFAULT 'normal',
    quantidade INT DEFAULT 1,
    adicionado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_cliente) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (id_produto) REFERENCES produtos(id) ON DELETE CASCADE
) ENGINE=InnoDB;


CREATE TABLE IF NOT EXISTS pedidos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_cliente INT NOT NULL,
    id_mesa INT DEFAULT NULL,
    tipo_pedido ENUM('balcao', 'mesa') DEFAULT 'balcao',
    total DECIMAL(10,2) NOT NULL,
    status ENUM('pendente', 'preparando', 'pronto', 'entregue', 'cancelado') DEFAULT 'pendente',
    observacoes TEXT,
    numero_mesa VARCHAR(10) DEFAULT NULL,
    tipo_retirada ENUM('mesa', 'balcao') DEFAULT 'balcao',
    conta_solicitada TINYINT(1) DEFAULT 0,
    conta_solicitada_em TIMESTAMP NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_cliente) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_status (status)
) ENGINE=InnoDB;


CREATE TABLE IF NOT EXISTS pedido_itens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_pedido INT NOT NULL,
    id_produto INT NOT NULL,
    quantidade INT NOT NULL,
    preco_unitario DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (id_pedido) REFERENCES pedidos(id) ON DELETE CASCADE,
    FOREIGN KEY (id_produto) REFERENCES produtos(id)
) ENGINE=InnoDB;


CREATE TABLE IF NOT EXISTS mesas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    numero VARCHAR(10) UNIQUE NOT NULL,
    qr_code VARCHAR(255),
    ocupada TINYINT(1) DEFAULT 0,
    ativa TINYINT(1) DEFAULT 1,
    criada_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;




INSERT INTO usuarios (nome, email, senha, tipo) VALUES
('Farmacêutico Responsável', 'admin@farmavida.com', '$2y$10$vQHFzXQf5tLvXPxXyLJNk.a5gXZ3LHZcFYxGCLxKmFN6uLYm5YiCS', 'dono');






INSERT INTO produtos (nome, descricao, preco, categoria, disponivel) VALUES
('Dipirona Monoidratada 500mg', 'Analgésico e antitérmico. Indicado para dores em geral e febre. Caixa com 20 comprimidos.', 8.90, 'Medicamentos', 1),
('Paracetamol 750mg', 'Analgésico e antitérmico sem contraindicações para a maioria dos pacientes. Caixa com 20 comprimidos.', 7.50, 'Medicamentos', 1),
('Ibuprofeno 600mg', 'Anti-inflamatório, analgésico e antitérmico. Eficaz em dores musculares e articulares. Cx 20 comp.', 14.90, 'Medicamentos', 1),
('Amoxicilina 500mg', 'Antibiótico de amplo espectro. Uso sob prescrição médica. Caixa com 21 cápsulas.', 22.90, 'Medicamentos', 1),
('Omeprazol 20mg', 'Protetor gástrico. Indicado para gastrite, refluxo e úlcera. Caixa com 28 cápsulas.', 18.50, 'Medicamentos', 1),
('Losartana Potássica 50mg', 'Anti-hipertensivo. Controla a pressão arterial. Caixa com 30 comprimidos. Com prescrição.', 24.90, 'Medicamentos', 1),
('Metformina 850mg', 'Antidiabético. Controle da glicemia no diabetes tipo 2. Cx 30 comp. Com prescrição.', 12.90, 'Medicamentos', 1),
('Atorvastatina 20mg', 'Redutor de colesterol. Previne doenças cardiovasculares. Cx 30 comp. Com prescrição.', 31.50, 'Medicamentos', 1);


INSERT INTO produtos (nome, descricao, preco, categoria, disponivel) VALUES
('Simeticona 40mg (Genérico)', 'Antiflatulento. Elimina gases intestinais. Caixa com 50 comprimidos.', 6.90, 'Genéricos', 1),
('Loratadina 10mg (Genérico)', 'Antialérgico. Alivia rinite, urticária e alergias cutâneas. Caixa com 12 comprimidos.', 9.90, 'Genéricos', 1),
('Fluconazol 150mg (Genérico)', 'Antifúngico. Tratamento de candidíase. 1 cápsula. Com prescrição médica.', 11.90, 'Genéricos', 1),
('Ranitidina 150mg (Genérico)', 'Antiulceroso. Trata e previne úlceras gástricas e duodenais. Cx 20 comp.', 8.50, 'Genéricos', 1);


INSERT INTO produtos (nome, descricao, preco, categoria, disponivel) VALUES
('Vitamina C 1000mg Efervescente', 'Reforço imunológico. Rico em Vitamina C com sabor laranja. Tubo com 10 comprimidos efervescentes.', 19.90, 'Vitaminas', 1),
('Vitamina D3 2000UI', 'Essencial para ossos e sistema imune. Gotas de fácil absorção. Frasco 20ml = 200 doses.', 34.90, 'Vitaminas', 1),
('Complexo B + Ferro', 'Combinação de vitaminas do complexo B com ferro. Combate cansaço e anemia. Frasco 60 cápsulas.', 29.90, 'Vitaminas', 1),
('Ômega 3 1000mg TG', 'Triglicerídeos de alta qualidade. Saúde cardiovascular e cerebral. Frasco com 120 cápsulas.', 59.90, 'Vitaminas', 1),
('Magnésio Quelato 400mg', 'Mineral essencial para músculos, nervos e sono de qualidade. Frasco 60 comprimidos.', 42.90, 'Vitaminas', 1),
('Zinco + Vitamina C', 'Dupla para imunidade. Auxilia na cicatrização e combate infecções. Caixa 30 comprimidos.', 24.50, 'Vitaminas', 1);


INSERT INTO produtos (nome, descricao, preco, categoria, disponivel) VALUES
('Protetor Solar FPS 60 – 120ml', 'Proteção solar UVA e UVB, fórmula não oleosa, resistente à água. Ideal para uso diário.', 38.90, 'Higiene Pessoal', 1),
('Antisséptico Bucal 500ml', 'Fórmula sem álcool. Elimina 99,9% das bactérias. Deixa o hálito fresco por até 12h.', 18.90, 'Higiene Pessoal', 1),
('Sabonete Íntimo pH Controlado', 'pH neutro ideal para higiene íntima feminina. Sem parabenos. Frasco 200ml.', 22.90, 'Higiene Pessoal', 1),
('Absorvente Noturno Com Abas (8un)', 'Cobertura extra para a noite, com abas de proteção e textura suave.', 11.90, 'Higiene Pessoal', 1),
('Repelente Spray 100ml', 'Proteção eficaz contra mosquitos. DEET 15%. Validade 8h. Ideal para crianças acima de 3 anos.', 27.90, 'Higiene Pessoal', 1),
('Alcool Gel 70% 500g', 'Higienizador de mãos. Bactericida e antifúngico. Elimina 99,9% dos germes.', 15.90, 'Higiene Pessoal', 1);


INSERT INTO produtos (nome, descricao, preco, categoria, disponivel) VALUES
('Hidratante Corporal Uréia 10% – 500ml', 'Hidratação intensa para peles secas e ressecadas. Com ureia e glicerina. Indicado por dermatologistas.', 49.90, 'Dermocosméticos', 1),
('Sérum Vitamina C Facial 30ml', 'Uniformiza o tom da pele, reduz manchas e estimula o colágeno. Uso diário.', 79.90, 'Dermocosméticos', 1),
('Shampoo Antiqueda – 300ml', 'Fórmula com biotina, saw palmetto e niacinamida. Reduz a queda e fortalece os fios.', 44.90, 'Dermocosméticos', 1),
('Gel Cicatrizante Bepantol 30g', 'Promove a regeneração da pele. Indicado para assaduras, feridas e queimaduras leves.', 32.90, 'Dermocosméticos', 1),
('Creme para Mãos Neutrogena 50g', 'Hidratação intensiva para mãos ressecadas. Com glicerina e vitamina E.', 19.90, 'Dermocosméticos', 1);


INSERT INTO produtos (nome, descricao, preco, categoria, disponivel) VALUES
('Dipirona Gotas Infantil 500mg/ml', 'Analgésico e antitérmico em gotas. Frasco 10ml. Indicado para bebês a partir de 3 meses.', 12.90, 'Infantil', 1),
('Vitamina D3 400UI Gotas para Bebê', 'Vitamina D para lactentes. Dose de 5 gotas/dia. Frasco 10ml = 200 doses.', 28.90, 'Infantil', 1),
('Soro Fisiológico Nasal 10 Flaconetes', 'Lavagem nasal para bebês e crianças. Alivia nariz congestionado. 10x5ml.', 16.90, 'Infantil', 1),
('Creme para Assaduras 45g', 'Protetor e cicatrizante. Forma barreira protetora. Indicado a partir do nascimento.', 21.90, 'Infantil', 1),
('Termômetro Digital Clínico', 'Resultado em 10 segundos. Alarme de febre. Memória do último resultado.', 29.90, 'Infantil', 1);


INSERT INTO produtos (nome, descricao, preco, categoria, disponivel) VALUES
('Melatonina 0,2mg 60 Comprimidos', 'Auxilia no ritmo circadiano e melhora a qualidade do sono. Sem dependência ou sedação.', 39.90, 'Bem-Estar', 1),
('Colágeno Tipo II + Vitamina C 60cap', 'Suporte para articulações e cartilagens. Melhora mobilidade e reduz dor articular.', 64.90, 'Bem-Estar', 1),
('Cloreto de Magnésio PA 33 Comprimidos', 'Suplemento de magnésio altamente biodisponível. Cansaço, câimbras e estresse.', 18.90, 'Bem-Estar', 1),
('Probiótico Lactobacillus 30 Sachês', '10 bilhões de UFC por dose. Equilíbrio da flora intestinal e imunidade.', 74.90, 'Bem-Estar', 1),
('Chá de Camomila Orgânico 15 Sachês', 'Propriedades calmantes e digestivas. 100% natural e orgânico certificado.', 12.90, 'Bem-Estar', 1);


INSERT INTO produtos (nome, descricao, preco, categoria, disponivel) VALUES
('Curativo Adesivo Sortido (50 unidades)', 'Sortimento de tamanhos. Hipoalergênico, impermeável e transpirável.', 14.90, 'Primeiros Socorros', 1),
('Álcool 70% INPM 1 Litro', 'Antisséptico e desinfetante hospitalar. Ativa contra bactérias, fungos e vírus.', 18.90, 'Primeiros Socorros', 1),
('Água Oxigenada 10 vol. 100ml', 'Antisséptico para higienização de feridas. Auxilia na limpeza e cicatrização.', 5.90, 'Primeiros Socorros', 1),
('Atadura Crepe 10cm x 4,5m (Par)', 'Atadura elástica de crepe. Para imobilização e compressão. Par com 2 unidades.', 9.90, 'Primeiros Socorros', 1),
('Kit Primeiros Socorros Completo', 'Contém: curativo, tesoura, pinça, esparadrapo, atadura, álcool e luvas. Estojo resistente.', 54.90, 'Primeiros Socorros', 1);


INSERT INTO produtos (nome, descricao, preco, categoria, disponivel) VALUES
('Joelheira Elástica Ortopédica M', 'Suporte e compressão para o joelho. Tecido respirável e anatômico.', 39.90, 'Ortopedia', 1),
('Bengala Ajustável com 4 Pontos', 'Maior estabilidade e segurança. Regulável de 79 a 93cm. Suporta até 100kg.', 89.90, 'Ortopedia', 1),
('Palmilha Ortopédica em Gel', 'Absorção de impacto e alívio de dores nos pés. Par. Tamanho 35-44 (cortável).', 29.90, 'Ortopedia', 1);




INSERT INTO mesas (numero, qr_code, ativa) VALUES
('G1', 'QR_GUICHE_1', 1),
('G2', 'QR_GUICHE_2', 1),
('G3', 'QR_GUICHE_3', 1),
('G4', 'QR_GUICHE_4', 1),
('G5', 'QR_GUICHE_5', 1);




SELECT 'Usuários' as tabela, COUNT(*) as total FROM usuarios
UNION ALL SELECT 'Produtos', COUNT(*) FROM produtos
UNION ALL SELECT 'Categorias', COUNT(DISTINCT categoria) FROM produtos;

SELECT categoria, COUNT(*) as qtd_produtos FROM produtos GROUP BY categoria ORDER BY categoria;






