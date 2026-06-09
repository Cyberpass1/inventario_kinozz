-- MySQL dump 10.13  Distrib 8.0.46, for Linux (x86_64)
--
-- Host: localhost    Database: inventory_test
-- ------------------------------------------------------
-- Server version	8.0.46

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `cash_accounts`
--

DROP TABLE IF EXISTS `cash_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cash_accounts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `account_code` varchar(20) NOT NULL,
  `account_name` varchar(120) NOT NULL,
  `method_type` varchar(40) NOT NULL,
  `currency_code` varchar(10) NOT NULL,
  `opening_balance` decimal(14,2) NOT NULL DEFAULT '0.00',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_cash_account_method_currency` (`method_type`,`currency_code`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cash_accounts`
--

LOCK TABLES `cash_accounts` WRITE;
/*!40000 ALTER TABLE `cash_accounts` DISABLE KEYS */;
INSERT INTO `cash_accounts` VALUES (1,'1011201','Punto de venta USD','point_of_sale','USD',0.00,1,'2026-04-03 14:02:33','2026-04-03 14:02:33'),(2,'1011202','Punto de venta VES','point_of_sale','VES',0.00,1,'2026-04-03 14:02:33','2026-04-03 14:02:33'),(3,'1011101','Caja USD','cash','USD',150.00,1,'2026-04-03 14:02:33','2026-04-03 14:08:14'),(4,'1011102','Caja VES','cash','VES',44000.00,1,'2026-04-03 14:02:33','2026-04-03 14:08:14'),(5,'1011301','Transferencia USD','bank_transfer','USD',0.00,1,'2026-04-03 14:02:33','2026-04-03 14:02:33'),(6,'1011302','Transferencia VES','bank_transfer','VES',0.00,1,'2026-04-03 14:02:33','2026-04-03 14:02:33'),(7,'1011401','Pago movil USD','mobile_payment','USD',0.00,1,'2026-04-03 14:02:33','2026-04-03 14:02:33'),(8,'1011402','Pago movil VES','mobile_payment','VES',0.00,1,'2026-04-03 14:02:33','2026-04-03 14:02:33'),(9,'1011501','Billetera USDT USD','usdt','USD',0.00,1,'2026-04-03 14:02:33','2026-04-03 14:02:33'),(10,'1011502','Billetera USDT VES','usdt','VES',0.00,1,'2026-04-03 14:02:33','2026-04-03 14:02:33'),(11,'1011601','Zelle USD','zelle','USD',0.00,1,'2026-04-03 14:02:33','2026-04-03 14:02:33'),(12,'1011602','Zelle VES','zelle','VES',0.00,1,'2026-04-03 14:02:33','2026-04-03 14:02:33');
/*!40000 ALTER TABLE `cash_accounts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cash_movements`
--

DROP TABLE IF EXISTS `cash_movements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cash_movements` (
  `id` int NOT NULL AUTO_INCREMENT,
  `cash_account_id` int NOT NULL,
  `movement_date` date NOT NULL,
  `direction` varchar(10) NOT NULL,
  `currency_code` varchar(10) NOT NULL,
  `exchange_rate` decimal(14,4) NOT NULL DEFAULT '1.0000',
  `amount_original` decimal(14,2) NOT NULL,
  `amount_converted` decimal(14,2) NOT NULL,
  `source_type` varchar(40) DEFAULT NULL,
  `source_id` int DEFAULT NULL,
  `reference` varchar(120) NOT NULL,
  `notes` text,
  `is_reversed` tinyint(1) NOT NULL DEFAULT '0',
  `reversed_at` datetime DEFAULT NULL,
  `reversal_reason` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_cash_movements_account` (`cash_account_id`),
  CONSTRAINT `fk_cash_movements_account` FOREIGN KEY (`cash_account_id`) REFERENCES `cash_accounts` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=53 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cash_movements`
--

LOCK TABLES `cash_movements` WRITE;
/*!40000 ALTER TABLE `cash_movements` DISABLE KEYS */;
INSERT INTO `cash_movements` VALUES (1,8,'2026-04-08','in','VES',475.0083,18525.32,18525.32,'delivery_note_payment',1,'COBRO INICIAL PAGO MOVIL','',0,NULL,NULL,'2026-04-08 04:47:59'),(2,8,'2026-04-09','out','VES',475.9583,2284599.84,2284599.84,'expense',1,'N/A','Gasolina, busqueda de tela',1,'2026-04-09 06:21:04',NULL,'2026-04-09 13:18:00'),(3,8,'2026-04-09','out','VES',475.9583,4759.58,4759.58,'expense',2,'N/A','',0,NULL,NULL,'2026-04-09 13:22:00'),(4,3,'2026-04-10','out','USD',477.1488,115.02,54881.65,'purchase_payment',1,'N/A','',0,NULL,NULL,'2026-04-11 00:57:30'),(5,4,'2026-04-11','in','VES',477.1488,192290.97,192290.97,'invoice_payment',1,'N/A','',0,NULL,NULL,'2026-04-11 17:19:17'),(6,8,'2026-04-11','in','VES',477.1488,16570.63,16570.63,'invoice_payment',2,'N/A','',0,NULL,NULL,'2026-04-11 17:22:00'),(7,8,'2026-04-11','in','VES',477.1488,12405.87,12405.87,'invoice_payment',3,'N/A','',0,NULL,NULL,'2026-04-11 17:23:37'),(8,8,'2026-04-11','in','VES',477.1488,12405.87,12405.87,'invoice_payment',4,'N/A','',0,NULL,NULL,'2026-04-11 17:26:50'),(9,8,'2026-04-11','out','VES',477.1488,20517.40,20517.40,'expense',3,'N/A','PAGO YAMIRE CONFECCIÓN 30 FRANELAS',0,NULL,NULL,'2026-04-11 17:28:01'),(10,3,'2026-04-12','out','USD',477.1488,65.00,31014.67,'expense',4,'N/A','PAGO NOMINA BRAYAN',0,NULL,NULL,'2026-04-12 18:23:22'),(11,8,'2026-04-12','in','VES',477.1488,12405.86,12405.86,'invoice_payment',5,'COBRO INICIAL PAGO MOVIL','',0,NULL,NULL,'2026-04-12 20:19:15'),(12,8,'2026-04-13','in','VES',477.1488,149000.00,149000.00,'invoice_payment',6,'N/A','',0,NULL,NULL,'2026-04-13 10:30:35'),(13,8,'2026-04-13','in','VES',477.1488,6202.93,6202.93,'invoice_payment',7,'N/A','',0,NULL,NULL,'2026-04-13 18:33:44'),(14,8,'2026-04-17','in','VES',480.2572,12443.10,12443.10,'invoice_payment',8,'N/A','',0,NULL,NULL,'2026-04-17 18:05:22'),(15,7,'2026-04-18','out','USD',481.2177,10.00,4812.18,'expense',5,'N/A',NULL,0,NULL,NULL,'2026-04-18 22:47:27'),(16,7,'2026-04-18','out','USD',481.2177,28.00,13474.10,'expense',6,'N/A',NULL,0,NULL,NULL,'2026-04-18 22:51:14'),(17,4,'2026-04-18','in','VES',481.2177,12511.66,12511.66,'invoice_payment',9,'COBRO INICIAL EFECTIVO','',0,NULL,NULL,'2026-04-18 22:54:01'),(18,8,'2026-04-18','in','VES',481.2177,6255.83,6255.83,'invoice_payment',10,'COBRO INICIAL PAGO MOVIL','',0,NULL,NULL,'2026-04-18 22:59:23'),(19,4,'2026-04-20','in','VES',481.2177,18767.49,18767.49,'invoice_payment',11,'COBRO INICIAL EFECTIVO','',0,NULL,NULL,'2026-04-20 19:37:13'),(20,4,'2026-04-20','in','VES',481.2177,7218.26,7218.26,'invoice_payment',12,'COBRO INICIAL EFECTIVO','',0,NULL,NULL,'2026-04-20 19:39:34'),(21,8,'2026-04-22','in','VES',482.7586,12443.10,12443.10,'invoice_payment',13,'N/A','',0,NULL,NULL,'2026-04-22 14:47:44'),(22,8,'2026-04-22','in','VES',482.7586,109103.36,109103.36,'invoice_payment',14,'N/A','',0,NULL,NULL,'2026-04-22 15:03:41'),(23,8,'2026-04-22','in','VES',482.7586,21241.36,21241.36,'invoice_payment',15,'N/A','',0,NULL,NULL,'2026-04-22 15:04:26'),(24,7,'2026-04-22','out','USD',483.3379,36.00,17400.16,'expense',7,'N/A',NULL,0,NULL,NULL,'2026-04-22 17:17:31'),(25,7,'2026-04-22','out','USD',483.3379,40.00,19333.52,'expense',8,'N/A','Pago cableado electrico 220',0,NULL,NULL,'2026-04-22 17:18:49'),(26,7,'2026-04-23','out','USD',483.3379,3.00,1450.01,'expense',9,'N/A','',0,NULL,NULL,'2026-04-23 17:22:43'),(27,8,'2026-04-23','in','VES',483.3379,31416.95,31416.95,'invoice_payment',16,'N/A','',0,NULL,NULL,'2026-04-23 17:31:34'),(28,7,'2026-04-23','out','USD',483.3379,17.00,8216.74,'expense',10,'N/A',NULL,0,NULL,NULL,'2026-04-23 17:32:55'),(29,4,'2026-04-23','in','VES',483.3379,10150.11,10150.11,'invoice_payment',17,'COBRO INICIAL EFECTIVO','',0,NULL,NULL,'2026-04-23 17:42:46'),(30,8,'2026-05-04','in','VES',489.5547,25456.84,25456.84,'invoice_payment',18,'N/A','',0,NULL,NULL,'2026-05-04 16:08:23'),(31,7,'2026-05-04','out','USD',489.5547,80.00,39164.38,'expense',11,'n/a','PAGO BRAYAN',0,NULL,NULL,'2026-05-04 16:25:23'),(32,7,'2026-05-04','out','USD',489.5547,40.00,19582.19,'expense',12,'n/a','PAGO ROBERTO',0,NULL,NULL,'2026-05-04 16:26:56'),(33,7,'2026-05-04','out','USD',489.5547,30.00,14686.64,'expense',13,'n/a','',0,NULL,NULL,'2026-05-04 16:28:10'),(34,7,'2026-05-04','out','USD',489.5547,25.00,12238.87,'expense',14,'n/a',NULL,0,NULL,NULL,'2026-05-04 16:29:05'),(35,3,'2026-05-04','out','USD',489.5547,193.20,94581.97,'purchase_payment',2,'n/a','',0,NULL,NULL,'2026-05-04 16:33:32'),(36,4,'2026-05-04','in','VES',489.5547,25456.60,25456.60,'invoice_payment',19,'COBRO INICIAL EFECTIVO','',0,NULL,NULL,'2026-05-04 16:34:55'),(37,8,'2026-05-04','in','VES',489.5547,0.24,0.24,'invoice_payment',20,'n/a','',0,NULL,NULL,'2026-05-04 16:39:19'),(38,8,'2026-05-04','in','VES',489.5547,3671.66,3671.66,'invoice_payment',21,'n/a','',0,NULL,NULL,'2026-05-04 16:39:34'),(39,8,'2026-05-04','in','VES',489.5547,6364.21,6364.21,'invoice_payment',22,'n/a','',0,NULL,NULL,'2026-05-04 16:39:48'),(40,8,'2026-05-04','in','VES',489.5547,12728.30,12728.30,'invoice_payment',23,'COBRO INICIAL PAGO MOVIL','',0,NULL,NULL,'2026-05-04 16:50:50'),(41,4,'2026-05-04','in','VES',489.5547,0.12,0.12,'invoice_payment',24,'n/a','',0,NULL,NULL,'2026-05-04 16:52:06'),(42,8,'2026-05-04','in','VES',489.5547,6364.21,6364.21,'invoice_payment',25,'n/a','',0,NULL,NULL,'2026-05-04 16:52:20'),(43,8,'2026-05-04','out','VES',490.0442,10150.00,10150.00,'expense',15,'N/A','',0,NULL,NULL,'2026-05-05 01:08:48'),(44,8,'2026-05-20','in','VES',520.9142,142209.48,142209.48,'invoice_payment',26,'n/a','',0,NULL,NULL,'2026-05-20 13:51:12'),(45,8,'2026-05-20','in','VES',520.9142,33859.15,33859.15,'invoice_payment',27,'n/a','',0,NULL,NULL,'2026-05-20 13:54:49'),(46,3,'2026-05-20','out','USD',520.9142,96.00,50007.76,'expense',16,'n/a','',0,NULL,NULL,'2026-05-20 13:57:02'),(47,7,'2026-05-20','out','USD',520.9142,50.00,26045.71,'expense',17,'n/a','Nomina Roberto',0,NULL,NULL,'2026-05-20 13:57:50'),(48,7,'2026-05-20','out','USD',520.9142,80.00,41673.14,'expense',18,'n/a','Nomina Brayan',0,NULL,NULL,'2026-05-20 13:59:21'),(49,8,'2026-05-20','out','VES',520.9142,13522.00,13522.00,'expense',19,'n/a','',0,NULL,NULL,'2026-05-20 14:04:25'),(50,4,'2026-05-28','in','VES',544.5794,0.25,0.25,'invoice_payment',28,'n/a','',0,NULL,NULL,'2026-05-28 14:09:42'),(51,8,'2026-05-28','in','VES',544.5794,7079.53,7079.53,'invoice_payment',29,'0102','',0,NULL,NULL,'2026-05-28 15:17:41'),(52,8,'2026-05-28','in','VES',544.5794,7079.53,7079.53,'invoice_payment',30,'0102','',0,NULL,NULL,'2026-05-28 15:20:15');
/*!40000 ALTER TABLE `cash_movements` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-06-04 13:02:42
