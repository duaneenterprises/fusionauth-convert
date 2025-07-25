/*
 * Copyright (c) 2020, FusionAuth, All Rights Reserved
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND,
 * either express or implied. See the License for the specific
 * language governing permissions and limitations under the License.
 */
package com.leaguejoe.plugins;

import io.fusionauth.plugin.spi.security.PasswordEncryptor;
import org.testng.Assert;
import org.testng.annotations.Test;

/**
 * @author Daniel DeGroff
 */
public class LeageJoePasswordEncryptorTest {
  @Test
  public void encrypt() {
    // TODO : Assert that a plain text password matches an expected hash.
    // - This example code will assert correctly based upon the implementation in 'MyExamplePasswordEncryptor'
    PasswordEncryptor encryptor = new LeageJoePasswordEncryptor();
    Assert.assertEquals(encryptor.encrypt("HtZX28CECnaL", "18f", 24_000), "df6cb6dad23e6f5d98e3edfe7f3492a137f53041093ca6adb39bca588abfb6dd");
  }
}
