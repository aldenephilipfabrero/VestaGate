// Arduino sketch for a dual-relay solenoid gate.
// PHP sends: OPEN <milliseconds>
// Button on pin 4 is the only way to turn the solenoid off once opened.

const int RELAY_PIN_1 = 2;
const int RELAY_PIN_2 = 3;
const int BUTTON_PIN = 4;
const bool ACTIVE_HIGH_RELAY = true;
const unsigned long DEFAULT_OPEN_MS = 3000;

String incoming;

bool solenoidOpen = false;
bool buttonStableState = HIGH;
bool buttonLastReading = HIGH;
unsigned long buttonLastDebounceMs = 0;

void setRelay(bool on) {
  if (ACTIVE_HIGH_RELAY) {
    digitalWrite(RELAY_PIN_1, on ? HIGH : LOW);
    digitalWrite(RELAY_PIN_2, on ? HIGH : LOW);
  } else {
    digitalWrite(RELAY_PIN_1, on ? LOW : HIGH);
    digitalWrite(RELAY_PIN_2, on ? LOW : HIGH);
  }
}

void openSolenoid(unsigned long openMs) {
  (void)openMs;
  solenoidOpen = true;
  setRelay(true);
}

void closeSolenoid() {
  if (!solenoidOpen) {
    return;
  }

  setRelay(false);
  solenoidOpen = false;
  Serial.println("OK");
}

void updateButton() {
  bool reading = digitalRead(BUTTON_PIN);

  if (reading != buttonLastReading) {
    buttonLastDebounceMs = millis();
    buttonLastReading = reading;
  }

  if ((millis() - buttonLastDebounceMs) > 50) {
    if (reading != buttonStableState) {
      buttonStableState = reading;

      if (buttonStableState == LOW && solenoidOpen) {
        closeSolenoid();
      }
    }
  }
}

void setup() {
  pinMode(RELAY_PIN_1, OUTPUT);
  pinMode(RELAY_PIN_2, OUTPUT);
  pinMode(BUTTON_PIN, INPUT_PULLUP);

  setRelay(false);

  Serial.begin(9600);
  Serial.println("SOLENOID_READY");
}

void loop() {
  while (Serial.available() > 0) {
    char c = Serial.read();

    if (c == '\n' || c == '\r') {
      incoming.trim();

      if (incoming.startsWith("OPEN")) {
        unsigned long openMs = DEFAULT_OPEN_MS;
        int spaceIndex = incoming.indexOf(' ');

        if (spaceIndex > 0) {
          unsigned long requestedMs = incoming.substring(spaceIndex + 1).toInt();
          if (requestedMs > 0) {
            openMs = requestedMs;
          }
        }

        openSolenoid(openMs);
        Serial.print("OK OPEN "); Serial.println(openMs);
      } else if (incoming.startsWith("SET")) {
        // SET <pin> <value> - closing still requires the button on pin 4.
        int firstSpace = incoming.indexOf(' ');
        int secondSpace = incoming.indexOf(' ', firstSpace + 1);
        if (firstSpace > 0 && secondSpace > firstSpace) {
          int pin = incoming.substring(firstSpace + 1, secondSpace).toInt();
          int val = incoming.substring(secondSpace + 1).toInt();
          if (pin == RELAY_PIN_1 || pin == RELAY_PIN_2) {
            if (!val && solenoidOpen) {
              Serial.println("ERR USE_BUTTON_TO_CLOSE");
            } else {
              digitalWrite(pin, val ? HIGH : LOW);
              if (val) {
                solenoidOpen = true;
              }
              Serial.print("OK SET "); Serial.print(pin); Serial.print("="); Serial.println(val);
            }
          } else {
            Serial.println("ERR SET NOT_ALLOWED");
          }
        } else {
          Serial.println("ERR SET SYNTAX");
        }
      }

      incoming = "";
    } else {
      incoming += c;
    }
  }

  updateButton();
}
