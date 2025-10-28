# Multi-Scene Story Video Generator - Advanced Workflow

## Overview

**Workflow ID**: 16
**Name**: Multi-Scene Story Video Generator
**Category**: Video Production
**Complexity**: Advanced
**Total Steps**: 23
**Estimated Time**: 10-15 minutes

## Description

This advanced workflow generates complete video stories with AI-powered content creation, multi-scene visual generation, professional audio production, and automated video editing using FFmpeg.

## Workflow Capabilities

✅ **AI Story Generation** - Creative story concept development
✅ **Multi-Scene Breakdown** - Intelligent scene decomposition (5 scenes)
✅ **Image Generation** - High-quality 1920x1080 cinematic images per scene
✅ **Video Generation** - 5-second video clips per scene
✅ **Narration Audio** - Professional voice-over generation
✅ **Background Music** - Mood-appropriate cinematic music
✅ **FFmpeg Video Stitching** - Seamless scene concatenation
✅ **Audio Mixing** - Synchronized narration and background music
✅ **Final Video Production** - Complete edited video with audio

## Technical Requirements

- **FFmpeg**: Required for video stitching and audio mixing
- **AI Engines**: Text (GPT-4/Claude), Image (DALL-E/Stable Diffusion), Video (Synthesia/Heygen), Audio (ElevenLabs)
- **Storage**: Minimum 500MB for temporary files per execution
- **Processing Power**: High-performance GPU recommended for video generation

## Workflow Steps Breakdown

### Phase 1: Story Development (Steps 1-2)
1. **Generate Story Concept** (text)
   - Input: `{{story_theme}}` (e.g., "space exploration", "medieval adventure")
   - Output: `story_concept` - Compelling story with genre, conflict, and tone
   - Tokens: 500 max, Temperature: 0.8

2. **Break Story Into Scenes** (text)
   - Input: `{{story_concept}}`
   - Output: `scenes_breakdown` - JSON array of 5-8 scenes with location, action, mood, duration
   - Tokens: 1000 max, Temperature: 0.7

### Phase 2: Scene Generation (Steps 3-17)
Each scene follows this 3-step pattern:

**Scene 1 (Steps 3-5):**
3. Generate Scene 1 Detailed Description (text) → `scene1_description`
4. Generate Scene 1 Image (image, 1920x1080, HD) → `scene1_image`
5. Generate Scene 1 Video (video, 5 sec, 1920x1080, 30fps) → `scene1_video`

**Scene 2 (Steps 6-8):**
6. Generate Scene 2 Description → `scene2_description`
7. Generate Scene 2 Image → `scene2_image`
8. Generate Scene 2 Video → `scene2_video`

**Scene 3 (Steps 9-11):**
9. Generate Scene 3 Description → `scene3_description`
10. Generate Scene 3 Image → `scene3_image`
11. Generate Scene 3 Video → `scene3_video`

**Scene 4 (Steps 12-14):**
12. Generate Scene 4 Description → `scene4_description`
13. Generate Scene 4 Image → `scene4_image`
14. Generate Scene 4 Video → `scene4_video`

**Scene 5 (Steps 15-17):**
15. Generate Scene 5 Description → `scene5_description`
16. Generate Scene 5 Image → `scene5_image`
17. Generate Scene 5 Video → `scene5_video`

### Phase 3: Audio Production (Steps 18-20)
18. **Generate Narration Script** (text)
    - Input: `{{story_concept}}`
    - Output: `narration_script` - Professional voice-over script matching 5 scenes × 5 seconds
    - System Prompt: Professional voice-over script writer
    - Tokens: 500 max, Temperature: 0.7

19. **Generate Narration Audio** (audio)
    - Input: `{{narration_script}}`
    - Output: `narration_audio` - MP3 audio file
    - Voice: Professional
    - Speed: 1.0

20. **Generate Background Music** (audio)
    - Input: Story mood from `{{story_concept}}`
    - Output: `background_music` - 25-second cinematic MP3
    - Style: Cinematic, subtle, atmospheric

### Phase 4: Video Post-Production (Steps 21-22)
21. **Stitch Scene Videos (FFmpeg)** (code)
    - Input: `{{scene1_video}}` through `{{scene5_video}}`
    - Output: `stitched_video` → `/tmp/stitched_video.mp4`
    - Process:
      ```bash
      # Create concat file list
      cat > /tmp/concat_list.txt << EOF
      file '{{scene1_video}}'
      file '{{scene2_video}}'
      file '{{scene3_video}}'
      file '{{scene4_video}}'
      file '{{scene5_video}}'
      EOF

      # Concatenate videos
      ffmpeg -f concat -safe 0 -i /tmp/concat_list.txt \
          -c copy \
          -y /tmp/stitched_video.mp4
      ```

22. **Add Audio (Narration + Music)** (code)
    - Input: `{{narration_audio}}`, `{{background_music}}`, `{{stitched_video}}`
    - Output: `final_video` → `/tmp/final_story_video.mp4`
    - Process:
      ```bash
      # Mix narration and background music
      ffmpeg -i {{narration_audio}} -i {{background_music}} \
          -filter_complex "[0:a]volume=1.0[a1];[1:a]volume=0.3[a2];[a1][a2]amix=inputs=2:duration=first" \
          -y /tmp/mixed_audio.mp3

      # Add mixed audio to video
      ffmpeg -i {{stitched_video}} -i /tmp/mixed_audio.mp3 \
          -c:v copy -c:a aac -b:a 192k \
          -shortest \
          -y /tmp/final_story_video.mp4
      ```

### Phase 5: Metadata (Step 23)
23. **Generate Video Title & Description** (text)
    - Input: `{{story_concept}}`
    - Output: `video_metadata` - Engaging title and description for social media
    - System Prompt: Social media content specialist
    - Tokens: 200 max

## Workflow Configuration

```json
{
  "max_scenes": 8,
  "video_resolution": "1920x1080",
  "scene_duration": 5,
  "output_format": "mp4"
}
```

## Workflow Metadata

```json
{
  "complexity": "advanced",
  "requires_ffmpeg": true,
  "estimated_time": "10-15 minutes",
  "ai_engines_used": ["text", "image", "video", "audio"]
}
```

## Variable Mapping

### Input Variables
- `story_theme` - User-provided story theme/topic (e.g., "underwater adventure", "cyberpunk detective")

### Output Variables
- `story_concept` - Generated story concept
- `scenes_breakdown` - Scene breakdown JSON
- `scene1_description` through `scene5_description` - Scene visual descriptions
- `scene1_image` through `scene5_image` - Generated images (1920x1080)
- `scene1_video` through `scene5_video` - Generated video clips (5 sec each)
- `narration_script` - Voice-over script
- `narration_audio` - Narration audio file (MP3)
- `background_music` - Background music file (MP3, 25 sec)
- `stitched_video` - Concatenated scene videos
- `final_video` - Final edited video with audio
- `video_metadata` - Title and description for sharing

## Usage Example

1. **Access Templates**: Navigate to https://magic.homedev.cc/dashboard/user/workflows/templates
2. **Select Workflow**: Click on "Multi-Scene Story Video Generator"
3. **Execute**: Click "Execute Workflow"
4. **Provide Input**: Enter a story theme (e.g., "A robot's journey to discover emotions")
5. **Monitor Progress**: Watch the workflow execute through all 23 steps
6. **Download Result**: Retrieve the final video at `/tmp/final_story_video.mp4`

## Sample Story Themes

- "A time traveler's last mission"
- "Underwater civilization discovery"
- "Medieval dragon tamer adventure"
- "Space station emergency rescue"
- "AI becoming self-aware"
- "Fantasy forest guardian tale"
- "Cyberpunk detective noir"
- "Ancient temple exploration"

## Output Specifications

### Video Output
- **Resolution**: 1920x1080 (Full HD)
- **Frame Rate**: 30 fps
- **Duration**: ~25 seconds (5 scenes × 5 seconds)
- **Format**: MP4 (H.264 video, AAC audio)
- **Audio**: Stereo, 192 kbps

### Audio Levels
- **Narration**: Volume 1.0 (100%)
- **Background Music**: Volume 0.3 (30%)
- **Mix**: First input duration (narration dictates length)

## Troubleshooting

### Common Issues

**Issue**: FFmpeg command fails
**Solution**: Ensure FFmpeg is installed and accessible in system PATH

**Issue**: Video generation timeout
**Solution**: Increase workflow timeout in settings, reduce scene count

**Issue**: Audio desynchronization
**Solution**: Check narration script length matches video duration (25 sec)

**Issue**: Missing scene videos
**Solution**: Verify all AI engines are configured correctly in admin panel

## Performance Optimization

1. **Parallel Execution**: Consider splitting scene generation into parallel branches
2. **Caching**: Enable result caching for repeated story themes
3. **GPU Acceleration**: Use GPU-enabled video generation engines
4. **CDN Storage**: Store generated videos on CDN for faster delivery

## Credits and Attribution

- **Video Generation**: Synthesia, Heygen, Kling, or custom AI video engines
- **Image Generation**: DALL-E, Stable Diffusion, Midjourney
- **Text Generation**: GPT-4, Claude, Gemini
- **Audio Generation**: ElevenLabs, Google TTS
- **Video Processing**: FFmpeg (open-source)

## Future Enhancements

- [ ] Dynamic scene count (user-configurable 3-10 scenes)
- [ ] Multiple video resolutions (4K, vertical for mobile)
- [ ] Custom voice selection for narration
- [ ] Music genre selection
- [ ] Video effects and transitions
- [ ] Subtitle generation
- [ ] Multi-language support
- [ ] Real-time preview during generation

## Technical Notes

### FFmpeg Commands Explained

**Video Concatenation**:
```bash
ffmpeg -f concat -safe 0 -i /tmp/concat_list.txt -c copy -y output.mp4
```
- `-f concat`: Use concat demuxer
- `-safe 0`: Allow absolute file paths
- `-c copy`: Stream copy (no re-encoding for speed)
- `-y`: Overwrite output file without asking

**Audio Mixing**:
```bash
ffmpeg -i audio1.mp3 -i audio2.mp3 \
  -filter_complex "[0:a]volume=1.0[a1];[1:a]volume=0.3[a2];[a1][a2]amix=inputs=2:duration=first" \
  -y output.mp3
```
- `volume=1.0`: Set narration to 100%
- `volume=0.3`: Set music to 30% (background level)
- `amix=inputs=2`: Mix 2 audio streams
- `duration=first`: Use first input's duration

**Audio-Video Muxing**:
```bash
ffmpeg -i video.mp4 -i audio.mp3 \
  -c:v copy -c:a aac -b:a 192k -shortest \
  -y output.mp4
```
- `-c:v copy`: Copy video stream (no re-encoding)
- `-c:a aac`: Encode audio as AAC
- `-b:a 192k`: Set audio bitrate to 192 kbps
- `-shortest`: End output when shortest input ends

## Access Information

**Workflow URL**: https://magic.homedev.cc/dashboard/user/workflows/templates
**Workflow ID**: 16
**Template Status**: Public
**Created**: 2025-10-24

---

**Ready to create AI-generated story videos!** 🎬
