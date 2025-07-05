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

import java.nio.charset.StandardCharsets;
import java.security.MessageDigest;
import java.security.NoSuchAlgorithmException;

/**
 * This example code is a starting point to build your own hashing algorithm in order to import users into FusionAuth.
 *
 * @author Daniel DeGroff
 */
public class LeageJoePasswordEncryptor implements PasswordEncryptor {
  @Override
  public int defaultFactor() {
    return 1;
  }

  @Override
  public String encrypt(String password, String salt, int factor) {

    MessageDigest digest1;
    MessageDigest digest2;
    try {
      digest1 = MessageDigest.getInstance("SHA-256");
      digest2 = MessageDigest.getInstance("SHA-256");
    } catch (NoSuchAlgorithmException ex) {
      return null;
    }

    byte[] passwordBytes = password.getBytes(StandardCharsets.UTF_8);

    digest1.update(passwordBytes);

    byte[] passwordHash = digest1.digest();

    StringBuilder sb = new StringBuilder();
    for(int i=0; i< passwordHash.length ;i++)
    {
      sb.append(Integer.toString((passwordHash[i] & 0xff) + 0x100, 16).substring(1));
    }

    String saltAndPasswordHash = salt+sb.toString();
    digest2.update(saltAndPasswordHash.getBytes(StandardCharsets.UTF_8));

    byte[] hashedPassword = digest2.digest();

    sb = new StringBuilder();
    for(int i=0; i< hashedPassword.length ;i++)
    {
      sb.append(Integer.toString((hashedPassword[i] & 0xff) + 0x100, 16).substring(1));
    }

    String finalHash = sb.toString();

    return finalHash;

  }
}
