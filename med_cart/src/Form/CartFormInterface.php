<?php

namespace Drupal\med_cart\Form;

/**
 * Defines the behavior a cart form.
 */
interface CartFormInterface {
  const HOME_MSG = 'These services are not available at home, but can be obtained at our Center';
  const ENC_MSG = 'These services are not available in the Center, but can be accessed remotely';
  const LOGIN_MSG = "Log in to the Patient's Personal Account via the Unified Identification and Authentication System (GosUslugi) to be able to pay for services online and make an appointment with a doctor.";

}
