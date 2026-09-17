#include <SoftwareSerial.h>

// Arduino UNO + SIM900A
// D10 = RX <- SIM900A TX
// D11 = TX -> SIM900A RX

SoftwareSerial sim900(10, 11);

const char PHONE_NUMBER[] = "+639XXXXXXXXX";
const char SMS_MESSAGE[] = "Hello! This is a test SMS from my Arduino SIM900A Gateway.";

String inputBuffer = "";

void clearSIM900Buffer() {
  while (sim900.available()) {
    sim900.read();
  }
}

String readSIM900Response(unsigned long timeout) {
  String response = "";
  unsigned long startTime = millis();

  while (millis() - startTime < timeout) {
    while (sim900.available()) {
      char c = sim900.read();
      response += c;
      Serial.write(c);
    }
  }

  return response;
}

bool sendCommand(const char *command, const char *expected, unsigned long timeout) {
  clearSIM900Buffer();
  Serial.print("\n>> ");
  Serial.println(command);
  sim900.println(command);

  String response = readSIM900Response(timeout);

  if (response.indexOf(expected) >= 0) {
    Serial.print("OK: Found ");
    Serial.println(expected);
    return true;
  }

  Serial.print("ERROR: Expected ");
  Serial.print(expected);
  Serial.println(" was not received.");
  return false;
}

bool checkSIM900() {
  Serial.println();
  Serial.println("Checking SIM900A...");

  for (int i = 0; i < 3; i++) {
    if (sendCommand("AT", "OK", 2000)) {
      Serial.println("SIM900A communication OK.");
      return true;
    }
    delay(1000);
  }

  Serial.println("ERROR: SIM900A is not responding.");
  return false;
}

bool checkSIM() {
  Serial.println();
  Serial.println("Checking SIM card...");

  if (sendCommand("AT+CPIN?", "READY", 3000)) {
    Serial.println("SIM card is READY.");
    return true;
  }

  Serial.println("ERROR: SIM card is not ready.");
  return false;
}

bool checkNetwork() {
  Serial.println();
  Serial.println("Checking GSM network registration...");

  unsigned long startTime = millis();
  while (millis() - startTime < 30000) {
    clearSIM900Buffer();
    sim900.println("AT+CREG?");
    String response = readSIM900Response(2000);

    if (response.indexOf("+CREG: 0,1") >= 0 || response.indexOf("+CREG: 1,1") >= 0 ||
        response.indexOf("+CREG: 0,5") >= 0 || response.indexOf("+CREG: 1,5") >= 0) {
      Serial.println();
      Serial.println("GSM NETWORK REGISTERED.");
      return true;
    }

    Serial.println("Waiting for GSM network...");
    delay(2000);
  }

  Serial.println();
  Serial.println("ERROR: SIM900A did not register on the network.");
  return false;
}

String normalizePhone(String phone) {
  phone.trim();
  phone.replace(" ", "");

  if (phone.startsWith("+63")) return phone;
  if (phone.startsWith("0") && phone.length() == 11) return "+63" + phone.substring(1);
  if (phone.startsWith("63") && phone.length() >= 10) return "+" + phone;
  if (phone.length() >= 10) return "+63" + phone;
  return phone;
}

bool waitForText(const String &expected, unsigned long timeout) {
  String response = "";
  unsigned long startTime = millis();

  while (millis() - startTime < timeout) {
    while (sim900.available()) {
      char c = sim900.read();
      response += c;
      Serial.write(c);

      if (response.indexOf(expected) >= 0) {
        return true;
      }

      if (response.indexOf("ERROR") >= 0 || response.indexOf("CMS ERROR") >= 0 || response.indexOf("CME ERROR") >= 0) {
        return false;
      }
    }
    delay(20);
  }

  return false;
}

bool sendSMS(const String &phoneNumber, const String &message) {
  String smsPhone = normalizePhone(phoneNumber);

  if (!sendCommand("AT+CMGF=1", "OK", 3000)) {
    Serial.println("ERROR: Could not set SMS text mode.");
    return false;
  }

  if (!sendCommand("AT+CSCS=\"GSM\"", "OK", 3000)) {
    Serial.println("WARNING: GSM charset command failed; continuing.");
  }

  delay(500);
  clearSIM900Buffer();

  Serial.println();
  Serial.print("Sending to: ");
  Serial.println(smsPhone);
  Serial.print("Message: ");
  Serial.println(message);
  Serial.println();
  Serial.println("Requesting SMS prompt...");

  sim900.print("AT+CMGS=\"");
  sim900.print(smsPhone);
  sim900.println("\"");

  delay(1000);

  if (!waitForText(">", 10000)) {
    Serial.println();
    Serial.println("ERROR: SMS prompt '>' was not received.");
    return false;
  }

  Serial.println();
  Serial.println("SMS prompt received.");
  Serial.println("Sending message text...");

  sim900.print(message);
  delay(500);
  sim900.write(26);

  Serial.println();
  Serial.println("CTRL+Z sent.");
  Serial.println("Waiting for SIM900A to send SMS...");

  String response = readSIM900Response(20000);

  if (response.indexOf("+CMGS:") >= 0 && response.indexOf("OK") >= 0) {
    Serial.println();
    Serial.println("================================");
    Serial.println("       SMS SENT SUCCESSFULLY");
    Serial.println("================================");
    return true;
  }

  if (response.indexOf("+CMS ERROR") >= 0) {
    Serial.println();
    Serial.println("================================");
    Serial.println("          SMS FAILED");
    Serial.println("================================");
    return false;
  }

  if (response.indexOf("ERROR") >= 0) {
    Serial.println();
    Serial.println("================================");
    Serial.println("          SMS FAILED");
    Serial.println("================================");
    return false;
  }

  Serial.println();
  Serial.println("WARNING: No successful SMS response received.");
  return false;
}

void handleHostCommand(String cmd) {
  cmd.trim();

  if (cmd.startsWith("AT|")) {
    int firstPipe = cmd.indexOf('|');
    int secondPipe = cmd.indexOf('|', firstPipe + 1);
    if (firstPipe < 0 || secondPipe < 0) {
      Serial.println("ERROR:BAD_COMMAND");
      return;
    }

    String phone = cmd.substring(firstPipe + 1, secondPipe);
    String msg = cmd.substring(secondPipe + 1);
    phone.trim();
    msg.trim();

    if (phone.length() < 10 || msg.length() == 0) {
      Serial.println("ERROR:BAD_PHONE_OR_MESSAGE");
      return;
    }

    if (sendSMS(phone, msg)) {
      Serial.println("OK");
    } else {
      Serial.println("ERROR:SMS_FAILED");
    }
    return;
  }

  if (cmd.equalsIgnoreCase("TEST")) {
    if (checkSIM900() && checkSIM() && checkNetwork()) {
      Serial.println("OK:SIM_READY");
    } else {
      Serial.println("ERROR:SIM_NOT_READY");
    }
    return;
  }

  Serial.println("ERROR:UNKNOWN_COMMAND");
}

void setup() {
  Serial.begin(9600);
  sim900.begin(9600);
  delay(3000);

  Serial.println();
  Serial.println("================================");
  Serial.println("     SIM900A SMS GATEWAY");
  Serial.println("================================");
  Serial.println();
  Serial.println("SIM900A baud rate: 9600");
  Serial.println("Arduino RX: D10");
  Serial.println("Arduino TX: D11");

  if (!checkSIM900()) {
    Serial.println();
    Serial.println("STOPPING PROGRAM.");
    Serial.println("Check SIM900A wiring and power.");
    return;
  }

  if (!checkSIM()) {
    Serial.println();
    Serial.println("STOPPING PROGRAM.");
    Serial.println("Make sure the SIM card is inserted.");
    Serial.println("If SIM PIN is enabled, disable it first.");
    return;
  }

  if (!checkNetwork()) {
    Serial.println();
    Serial.println("STOPPING PROGRAM.");
    Serial.println("SIM900A could not register on GSM network.");
    return;
  }

  delay(2000);

  bool smsResult = sendSMS(String(PHONE_NUMBER), String(SMS_MESSAGE));
  Serial.println();
  if (smsResult) {
    Serial.println("SMS TEST COMPLETE.");
  } else {
    Serial.println("SMS TEST FAILED.");
  }

  Serial.println();
  Serial.println("================================");
  Serial.println("        SYSTEM FINISHED");
  Serial.println("================================");
}

void loop() {
  while (Serial.available() > 0) {
    char c = Serial.read();
    if (c == '\r' || c == '\n') {
      if (inputBuffer.length() > 0) {
        handleHostCommand(inputBuffer);
        inputBuffer = "";
      }
    } else {
      if (inputBuffer.length() < 256) {
        inputBuffer += c;
      }
    }
  }

  while (sim900.available()) {
    Serial.write(sim900.read());
  }

  delay(20);
}
