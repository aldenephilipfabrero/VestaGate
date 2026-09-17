// Debug Arduino sketch for solenoid gate
// Accepts OPEN <ms>, SET <pin> <0|1> (restricted), TEST commands
// Button on pin 4 is the only way to turn the solenoid off once opened.

const int RELAY_PIN_1 = 2;      // primary relay output
const int RELAY_PIN_2 = 3;      // secondary relay output (set -1 to disable)
const int BUTTON_PIN = 4;       // physical release button
const bool ACTIVE_HIGH_RELAY = true; // set to false if your module is active-low
const unsigned long DEFAULT_OPEN_MS = 3000;
const unsigned long BUTTON_DEBOUNCE_MS = 50;

String incoming;

// Latched open state. The relay stays powered until the button is pressed.
bool opening = false;
bool buttonStableState = HIGH;
bool buttonLastReading = HIGH;
unsigned long buttonLastDebounceMs = 0;

void setRelays(bool on) {
  int a = ACTIVE_HIGH_RELAY ? HIGH : LOW;
  int b = ACTIVE_HIGH_RELAY ? LOW : HIGH;
  // if active-high, on -> HIGH else LOW; invert for off
  digitalWrite(RELAY_PIN_1, on ? a : b);
  if (RELAY_PIN_2 >= 0) digitalWrite(RELAY_PIN_2, on ? a : b);
}

void beginOpen(unsigned long ms) {
  if (ms == 0) return;
  opening = true;
  Serial.print("DEBUG: Activating relay(s) for "); Serial.print(ms); Serial.println(" ms");
  setRelays(true);
}

void stopOpen() {
  if (opening) {
    setRelays(false);
    opening = false;
    Serial.println("DEBUG: Relay(s) deactivated");
    Serial.println("OK");
  }
}

void updateButton() {
  bool reading = digitalRead(BUTTON_PIN);

  if (reading != buttonLastReading) {
    buttonLastDebounceMs = millis();
    buttonLastReading = reading;
  }

  if ((millis() - buttonLastDebounceMs) > BUTTON_DEBOUNCE_MS) {
    if (reading != buttonStableState) {
      buttonStableState = reading;

      if (buttonStableState == LOW && opening) {
        Serial.println("DEBUG: Button pressed - shutting down relay(s)");
        stopOpen();
      }
    }
  }
}

void setup() {
  pinMode(RELAY_PIN_1, OUTPUT);
  if (RELAY_PIN_2 >= 0) pinMode(RELAY_PIN_2, OUTPUT);
  pinMode(BUTTON_PIN, INPUT_PULLUP);
  setRelays(false);

  Serial.begin(9600);
  delay(200);
  Serial.println("SOLENOID_DEBUG_READY");
}

void loop() {
  // Read serial input
  while (Serial.available() > 0) {
    char c = Serial.read();
    if (c == '\n' || c == '\r') {
      incoming.trim();
      if (incoming.length() > 0) {
        String cmd = incoming;
        cmd.trim();
        cmd.toUpperCase();
        Serial.print("DEBUG: Received command: "); Serial.println(cmd);

        if (cmd.startsWith("OPEN")) {
          unsigned long openMs = DEFAULT_OPEN_MS;
          int spaceIndex = cmd.indexOf(' ');
          if (spaceIndex > 0) {
            unsigned long requestedMs = (unsigned long)cmd.substring(spaceIndex + 1).toInt();
            if (requestedMs > 0) openMs = requestedMs;
          }
          beginOpen(openMs);

        } else if (cmd.startsWith("SET")) {
          // SET <pin> <0|1> — restricted to configured relay pins.
          // Closing the solenoid still requires the button on pin 4.
          int firstSpace = cmd.indexOf(' ');
          int secondSpace = cmd.indexOf(' ', firstSpace + 1);
          if (firstSpace > 0 && secondSpace > firstSpace) {
            int pin = cmd.substring(firstSpace + 1, secondSpace).toInt();
            int val = cmd.substring(secondSpace + 1).toInt();
            if (pin == RELAY_PIN_1 || pin == RELAY_PIN_2) {
              if (!val && opening) {
                Serial.println("ERR USE_BUTTON_TO_CLOSE");
              } else {
                digitalWrite(pin, val ? HIGH : LOW);
                if (val) {
                  opening = true;
                }
                Serial.print("OK SET "); Serial.print(pin); Serial.print("="); Serial.println(val);
              }
            } else {
              Serial.println("ERR SET NOT_ALLOWED");
            }
          } else {
            Serial.println("ERR SET SYNTAX");
          }

        } else if (cmd.equalsIgnoreCase("TEST")) {
          Serial.println("DEBUG: TEST - toggling relay(s)");
          beginOpen(500);

        } else {
          Serial.println("DEBUG: Unknown command");
        }
      }
      incoming = "";
    } else {
      incoming += c;
    }
  }

  updateButton();
}
